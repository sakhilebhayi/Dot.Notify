<?php

namespace Tests\Feature\Console;

use App\Models\NotifyBatch;
use App\Models\NotifyTemplate;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * dkp:publish-incident, this platform's Dot Knowledge Protocol pipeline
 * (see storage/app/private/README.md, platform.dkp.json, config/dkp.php).
 * Mirrors the real, already-proven Dot.Emall/Dot.Analytics precedent (same
 * envelope shape, same Ed25519 signing scheme) as a generic command over
 * any real, failed NotifyBatch row.
 */
class PublishDkpIncidentPackTest extends TestCase
{
    use RefreshDatabase;

    private string $outputDir;

    protected function setUp(): void
    {
        parent::setUp();

        $seed = random_bytes(SODIUM_CRYPTO_SIGN_SEEDBYTES);
        $keyPath = storage_path('app/private/dkp-signing-test.key');
        File::put($keyPath, base64_encode($seed));
        config(['dkp.signing_key_path' => $keyPath]);

        $this->outputDir = storage_path('app/dkp/packs-test');
        config(['dkp.output_path' => $this->outputDir]);
    }

    protected function tearDown(): void
    {
        File::delete(storage_path('app/private/dkp-signing-test.key'));
        File::deleteDirectory($this->outputDir);

        parent::tearDown();
    }

    private function requiredOptions(): array
    {
        return [
            '--root-cause' => 'A misconfigured send threshold let a low-priority class fire at 40x normal volume.',
            '--corrective-action' => 'Added a per-class rate ceiling at registration time.',
            '--corrective-owner' => 'Notify Platform Lead',
            '--corrective-due' => '2026-08-11',
            '--lesson' => 'Every notification class needs a rate ceiling checked before dispatch, not just at review time.',
        ];
    }

    public function test_it_publishes_a_signed_incident_report_for_a_failed_batch(): void
    {
        $team = Team::factory()->create();
        $template = NotifyTemplate::create([
            'team_id' => $team->id, 'name' => 'Alert Digest', 'subject' => 'Alert', 'body' => 'B', 'channel_type' => 'sms',
        ]);
        $batch = NotifyBatch::create([
            'team_id' => $team->id,
            'template_id' => $template->id,
            'name' => 'Threshold alert blast',
            'status' => 'failed',
            'total_recipients' => 500,
            'sent_count' => 12,
            'failed_count' => 488,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        $this->artisan('dkp:publish-incident', [
            'batch' => $batch->id,
            ...$this->requiredOptions(),
        ])->assertExitCode(0);

        $files = File::files($this->outputDir);
        $this->assertCount(1, $files);

        $pack = json_decode(File::get($files[0]->getPathname()), true);

        $this->assertSame('dot-notify', $pack['platform']);
        $this->assertSame('incident_report', $pack['payloads'][0]['payload_type']);
        $this->assertSame("dot-notify:batch-{$batch->id}", $pack['payloads'][0]['body']['incident_id']);
        $this->assertSame(['sms'], $pack['payloads'][0]['body']['impact']['systems']);
        $this->assertStringContainsString('488 of 500', $pack['payloads'][0]['body']['impact']['description']);
        $this->assertSame(
            'A misconfigured send threshold let a low-priority class fire at 40x normal volume.',
            $pack['payloads'][0]['body']['root_cause']['statement'],
        );
        $this->assertCount(1, $pack['signatures']);
    }

    public function test_the_written_pack_signature_verifies_against_the_committed_public_key_format(): void
    {
        $team = Team::factory()->create();
        $batch = NotifyBatch::create([
            'team_id' => $team->id,
            'name' => 'Blast',
            'status' => 'failed',
            'total_recipients' => 10,
            'sent_count' => 0,
            'failed_count' => 10,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);

        $this->artisan('dkp:publish-incident', [
            'batch' => $batch->id,
            ...$this->requiredOptions(),
        ])->assertExitCode(0);

        $files = File::files($this->outputDir);
        $pack = json_decode(File::get($files[0]->getPathname()), true);

        $seed = base64_decode(trim(File::get(config('dkp.signing_key_path'))), strict: true);
        $keypair = sodium_crypto_sign_seed_keypair($seed);
        $publicKey = sodium_crypto_sign_publickey($keypair);

        $signable = $pack;
        unset($signable['signatures']);
        $canonical = json_encode($this->sortKeysRecursive($signable), JSON_UNESCAPED_SLASHES);

        $signature = base64_decode($pack['signatures'][0]['value']);

        $this->assertTrue(sodium_crypto_sign_verify_detached($signature, $canonical, $publicKey));
    }

    public function test_it_refuses_to_publish_a_non_failed_batch(): void
    {
        $team = Team::factory()->create();
        $batch = NotifyBatch::create([
            'team_id' => $team->id,
            'name' => 'Blast',
            'status' => 'completed',
            'total_recipients' => 10,
            'sent_count' => 10,
            'failed_count' => 0,
            'completed_at' => now(),
        ]);

        $this->artisan('dkp:publish-incident', [
            'batch' => $batch->id,
            ...$this->requiredOptions(),
        ])->assertExitCode(1);

        $this->assertCount(0, File::exists($this->outputDir) ? File::files($this->outputDir) : []);
    }

    public function test_it_refuses_to_publish_a_batch_with_no_completed_at(): void
    {
        $team = Team::factory()->create();
        $batch = NotifyBatch::create([
            'team_id' => $team->id,
            'name' => 'Blast',
            'status' => 'failed',
            'total_recipients' => 10,
            'sent_count' => 0,
            'failed_count' => 10,
            'completed_at' => null,
        ]);

        $this->artisan('dkp:publish-incident', [
            'batch' => $batch->id,
            ...$this->requiredOptions(),
        ])->assertExitCode(1);
    }

    public function test_it_refuses_to_publish_without_required_postmortem_fields(): void
    {
        $team = Team::factory()->create();
        $batch = NotifyBatch::create([
            'team_id' => $team->id,
            'name' => 'Blast',
            'status' => 'failed',
            'total_recipients' => 10,
            'sent_count' => 0,
            'failed_count' => 10,
            'completed_at' => now(),
        ]);

        $this->artisan('dkp:publish-incident', ['batch' => $batch->id])
            ->assertExitCode(1);

        $this->assertCount(0, File::exists($this->outputDir) ? File::files($this->outputDir) : []);
    }

    public function test_it_errors_on_an_unknown_batch_id(): void
    {
        $this->artisan('dkp:publish-incident', [
            'batch' => 999999,
            ...$this->requiredOptions(),
        ])->assertExitCode(1);
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
}
