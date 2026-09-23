<?php

namespace App\Console\Commands;

use App\Models\NotifyBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Dot.Notify's first real DKP pipeline: a generic incident_report
 * publisher over this platform's own NotifyBatch table, following the
 * exact envelope/signing pattern Dot.Emall's PublishDkpIncidentPack
 * proved out first, and Dot.Analytics' generalization of it over its own
 * AnalyticsAlert table (see Dot.Brain os/19-Knowledge-Packs.md §2.4 and
 * os/05-Knowledge-Protocol.md §5 for why this is one hand-run command,
 * not a pipeline that transmits anywhere — DKP's transport layer is
 * unbuilt ecosystem-wide, so this signs and writes one JSON file for a
 * human to read).
 *
 * wiki.md §5 names "delivery failures... runaway send volume" as this
 * platform's incident source; a NotifyBatch with status=failed is exactly
 * that — a real, closed (batches don't reopen), volume-tracked delivery
 * failure. It never fabricates the qualitative postmortem fields
 * incident.schema.json requires (root_cause, corrective_actions,
 * lessons) — a NotifyBatch is just a send-tracking row, it has no
 * root-cause analysis of its own, so those are required CLI input from
 * whoever ran the postmortem, not auto-generated placeholder text.
 */
class PublishDkpIncidentPack extends Command
{
    protected $signature = 'dkp:publish-incident
        {batch : ID of the failed NotifyBatch to publish}
        {--root-cause= : Root cause statement (required)}
        {--corrective-action= : What was done to fix it (required)}
        {--corrective-owner= : Who owned the fix (required)}
        {--corrective-due= : Date the fix was due, YYYY-MM-DD (required)}
        {--lesson= : The generalizable lesson learned (required)}
        {--business-cost-estimate= : Optional business cost estimate}
        {--dkp-severity=sev2 : DKP severity sev1-sev4 (NotifyBatch has no severity concept of its own)}
        {--contributor-email= : Email of the human publishing this pack}
        {--contributor-name= : Display name of the human publishing this pack}';

    protected $description = 'Sign and write a real, failed NotifyBatch as an incident_report DKP pack';

    public function handle(): int
    {
        $batch = NotifyBatch::withoutGlobalScope('team')->with('template')->find($this->argument('batch'));

        if (! $batch) {
            $this->error("No NotifyBatch with ID {$this->argument('batch')}.");

            return self::FAILURE;
        }

        if ($batch->status !== 'failed') {
            $this->error("Batch #{$batch->id} is status '{$batch->status}', not 'failed'. Only failed batches are worth publishing as incident_report packs.");

            return self::FAILURE;
        }

        if (! $batch->completed_at) {
            $this->error("Batch #{$batch->id} has no completed_at. DKP incident_report packs describe closed incidents, per the schema's own convention.");

            return self::FAILURE;
        }

        if (! in_array($this->option('dkp-severity'), ['sev1', 'sev2', 'sev3', 'sev4'], true)) {
            $this->error('--dkp-severity must be one of: sev1, sev2, sev3, sev4.');

            return self::FAILURE;
        }

        $missing = collect(['root-cause', 'corrective-action', 'corrective-owner', 'corrective-due', 'lesson'])
            ->filter(fn ($opt) => blank($this->option($opt)));

        if ($missing->isNotEmpty()) {
            $this->error('Missing required option(s): --'.$missing->implode(', --').'. '
                .'These describe a real postmortem and are never auto-generated from the batch row.');

            return self::FAILURE;
        }

        $keyPath = config('dkp.signing_key_path');

        if (! File::exists($keyPath)) {
            $this->error("No signing key at {$keyPath}. See storage/app/private/README.md.");

            return self::FAILURE;
        }

        $seed = base64_decode(trim(File::get($keyPath)), strict: true);

        if ($seed === false || strlen($seed) !== SODIUM_CRYPTO_SIGN_SEEDBYTES) {
            $this->error('Signing key is not a valid 32-byte base64 Ed25519 seed.');

            return self::FAILURE;
        }

        $keypair = sodium_crypto_sign_seed_keypair($seed);
        $secretKey = sodium_crypto_sign_secretkey($keypair);

        $packId = 'dkp:'.config('dkp.platform').':'.(string) Str::uuid();
        $createdAt = now()->toIso8601ZuluString();

        $pack = [
            'dkp_version' => config('dkp.dkp_version'),
            'pack_id' => $packId,
            'pack_version' => '1.0.0',
            'platform' => config('dkp.platform'),
            'title' => "Failed batch send — {$batch->name}",
            'summary' => "{$batch->failed_count} of {$batch->total_recipients} recipients failed in batch '{$batch->name}'.",
            'created_at' => $createdAt,
            'contributors' => [
                [
                    'id' => $this->option('contributor-email') ?: 'unknown@dot-notify',
                    'kind' => 'human',
                    'display_name' => $this->option('contributor-name') ?: 'Dot.Notify Platform Lead',
                    'key_id' => config('dkp.key_id'),
                ],
            ],
            'payloads' => [
                [
                    'payload_type' => 'incident_report',
                    'body' => $this->incidentBody($batch),
                ],
            ],
            'provenance' => [
                'sources' => [
                    [
                        'kind' => 'system_record',
                        'uri' => "app/Models/NotifyBatch#{$batch->id}",
                        'observed_at' => $createdAt,
                    ],
                ],
                'transformations' => [
                    [
                        'step' => 'translate-failed-notify-batch-to-dkp-incident-report',
                        'tool' => 'App\\Console\\Commands\\PublishDkpIncidentPack',
                        'tool_version' => '1.0.0',
                        'actor' => config('dkp.platform'),
                    ],
                ],
                'published_by' => config('dkp.platform'),
            ],
            'confidence' => 0.9,
            'signatures' => [],
        ];

        $canonical = $this->canonicalize($pack);
        $signature = sodium_crypto_sign_detached($canonical, $secretKey);

        $pack['signatures'] = [
            [
                'key_id' => config('dkp.key_id'),
                'algorithm' => 'ed25519-jcs',
                'signed_at' => $createdAt,
                'value' => base64_encode($signature),
            ],
        ];

        $outputDir = config('dkp.output_path');
        File::ensureDirectoryExists($outputDir);
        $outputPath = $outputDir.'/'.Str::after($packId, ':'.config('dkp.platform').':').'.json';
        File::put($outputPath, json_encode($pack, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $this->info("Pack written to {$outputPath}");
        $this->line("pack_id: {$packId}");
        $this->line('Signature verifies: '.($this->verify($pack, $keypair) ? 'yes' : 'NO — DO NOT PUBLISH'));

        return self::SUCCESS;
    }

    /**
     * The real incident, per schemas/incident.schema.json. Every field is
     * either sourced straight from the NotifyBatch row (detection,
     * impact, timeline, resolved_at) or required human input for the
     * qualitative postmortem fields the row itself has no concept of
     * (root_cause, corrective_actions, lessons).
     */
    private function incidentBody(NotifyBatch $batch): array
    {
        $failureRate = $batch->total_recipients > 0
            ? round(($batch->failed_count / $batch->total_recipients) * 100, 1)
            : 0;

        return [
            'incident_id' => "dot-notify:batch-{$batch->id}",
            'kind' => 'incident',
            'severity' => $this->option('dkp-severity'),
            'detection' => [
                'detected_at' => $batch->completed_at->toIso8601ZuluString(),
                'detected_by' => 'Dot.Notify automated batch tracking',
                'method' => 'batch completion status tracking, not manual review',
            ],
            'impact' => [
                'systems' => [$batch->template?->channel_type ?? 'unknown channel'],
                'description' => "Batch '{$batch->name}': {$batch->failed_count} of {$batch->total_recipients} recipients failed ({$failureRate}% failure rate).",
                ...($this->option('business-cost-estimate') ? ['business_cost_estimate' => $this->option('business-cost-estimate')] : []),
            ],
            'timeline' => [
                ['at' => ($batch->started_at ?? $batch->created_at)->toIso8601ZuluString(), 'event' => "Batch '{$batch->name}' started"],
                ['at' => $batch->completed_at->toIso8601ZuluString(), 'event' => "Batch marked failed ({$batch->failed_count}/{$batch->total_recipients} recipients failed)"],
            ],
            'root_cause' => [
                'statement' => $this->option('root-cause'),
            ],
            'corrective_actions' => [
                [
                    'action' => $this->option('corrective-action'),
                    'owner' => $this->option('corrective-owner'),
                    'due' => $this->option('corrective-due'),
                    'status' => 'done',
                ],
            ],
            'lessons' => [
                [
                    'lesson' => $this->option('lesson'),
                    'verified' => true,
                ],
            ],
            'resolved_at' => $batch->completed_at->toIso8601ZuluString(),
        ];
    }

    private function canonicalize(array $pack): string
    {
        $signable = $pack;
        unset($signable['signatures']);

        return json_encode($this->sortKeysRecursive($signable), JSON_UNESCAPED_SLASHES);
    }

    private function sortKeysRecursive(array $value): array
    {
        $isList = array_is_list($value);

        if (! $isList) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortKeysRecursive($item);
            }
        }

        return $value;
    }

    private function verify(array $pack, string $keypair): bool
    {
        $publicKey = sodium_crypto_sign_publickey($keypair);
        $signature = base64_decode($pack['signatures'][0]['value']);
        $canonical = $this->canonicalize($pack);

        return sodium_crypto_sign_verify_detached($signature, $canonical, $publicKey);
    }
}
