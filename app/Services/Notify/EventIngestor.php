<?php

namespace App\Services\Notify;

use App\Jobs\DeliverNotifyLog;
use App\Models\NotifyChannel;
use App\Models\NotifyInboundEvent;
use App\Models\NotifyLog;
use App\Models\NotifyRule;
use App\Models\NotifyTemplate;
use App\Models\Team;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns one verified, validated service event into (at most) one queued NotifyLog.
 *
 * Reuses the team's existing rules/templates/channels: the company's active
 * rule for the event decides the template and channel. The caller never
 * supplies a template, and a channel only by naming one of ITS OWN company's
 * active channels. Rules are never created here (a company switches an event on
 * from the "Connected apps" page; see EventEnabler).
 *
 * Events in the catalogue (config/notify_events.php) have their `data` filtered
 * first: only the allowed keys survive, so nothing else is stored or rendered.
 * Events the catalogue does not know are accepted as before, unfiltered.
 */
class EventIngestor
{
    public function __construct(private readonly NotifyEventCatalogue $catalogue) {}

    /**
     * @param  array<string, mixed>  $data  validated request payload
     * @return array{0: NotifyInboundEvent, 1: bool} the event and whether it was a replay
     *
     * @throws UnknownChannel
     * @throws InvalidEventData
     */
    public function ingest(Team $team, array $data): array
    {
        $catalogued = $this->catalogue->find($data['event'], $data['platform']);
        $owner = $this->catalogue->owner($data['event']);
        $foreign = $owner !== null && $owner !== $data['platform'];
        $dropped = [];

        if ($catalogued) {
            ['data' => $data['data'], 'dropped' => $dropped] = $this->catalogue->inspect($data['platform'], $data['event'], (array) ($data['data'] ?? []));
        } elseif ($foreign) {
            // The event belongs to another platform: nothing this caller sent is kept.
            $data['data'] = [];
        }

        $existing = $this->find($team, $data);
        if ($existing) {
            return [$existing, true];
        }

        $override = $this->resolveChannelOverride($team, $data['channel'] ?? null);
        $sensitive = array_values($data['sensitive'] ?? []);

        try {
            $event = NotifyInboundEvent::withoutGlobalScopes()->create([
                'team_id' => $team->id,
                'notify_webhook_id' => null,
                'platform' => $data['platform'],
                'idempotency_key' => $data['idempotency_key'],
                // `sensitive` paths are relative to `data`; secret-looking keys are masked anywhere.
                'payload' => Redactor::payload($data, array_map(fn (string $path) => 'data.'.$path, $sensitive)) + ($dropped === [] ? [] : ['dropped_data_keys' => $dropped]),
                'source_event' => $data['event'],
                'trigger_event' => $data['event'],
                'verified_at' => now(),
                'status' => 'received',
            ]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent request with the same key won the race.
            return [$this->find($team, $data), true];
        }

        $this->route($event, $team, $data, $override, $sensitive, $catalogued, $foreign ? $owner : null);

        return [$event->fresh(), false];
    }

    /** @param  array<string, mixed>  $data */
    private function find(Team $team, array $data): ?NotifyInboundEvent
    {
        return NotifyInboundEvent::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('platform', $data['platform'])
            ->where('idempotency_key', $data['idempotency_key'])
            ->first();
    }

    /**
     * @throws UnknownChannel
     */
    private function resolveChannelOverride(Team $team, mixed $channel): ?NotifyChannel
    {
        if ($channel === null || $channel === '') {
            return null;
        }

        $query = NotifyChannel::withoutGlobalScopes()->where('team_id', $team->id)->where('is_active', true);

        $found = (is_int($channel) || ctype_digit((string) $channel))
            ? $query->whereKey((int) $channel)->first()
            : $query->where('name', (string) $channel)->orderBy('id')->first();

        return $found ?? throw new UnknownChannel;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $sensitive
     * @param  array<string, mixed>|null  $catalogued  the catalogue entry when the event is catalogued for this platform
     * @param  string|null  $foreignOwner  the platform that owns the event when this caller is not it
     */
    private function route(NotifyInboundEvent $event, Team $team, array $data, ?NotifyChannel $override, array $sensitive, ?array $catalogued, ?string $foreignOwner): void
    {
        if ($foreignOwner !== null) {
            $this->finish($event, 'no_rule', "\"{$data['event']}\" belongs to the {$foreignOwner} platform, not {$data['platform']}; nothing was sent.");

            return;
        }

        $rule = NotifyRule::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('trigger_event', $data['event'])
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $rule) {
            $this->finish($event, 'no_rule', "No active NotifyRule for trigger_event \"{$data['event']}\".");

            return;
        }

        if ($rule->isOverRateCeiling()) {
            $this->finish($event, 'ignored', "Rate ceiling ({$rule->rate_ceiling_per_hour}/hour) exceeded for trigger_event \"{$data['event']}\".");

            return;
        }

        // Even though the rule belongs to this team, make sure what it points at does too.
        $channel = $override ?? ($rule->channel_id ? NotifyChannel::withoutGlobalScopes()->find($rule->channel_id) : null);
        $template = $rule->template_id ? NotifyTemplate::withoutGlobalScopes()->find($rule->template_id) : null;

        if (! $channel || $channel->team_id !== $team->id || ! $channel->is_active) {
            $this->finish($event, 'failed', "The rule's channel is missing, inactive or belongs to another company.");

            return;
        }

        if (! $template || $template->team_id !== $team->id || ! $template->is_active) {
            $this->finish($event, 'failed', "The rule's template is missing, inactive or belongs to another company.");

            return;
        }

        $values = (array) ($data['data'] ?? []);
        $full = Redactor::flatten($values);
        $preview = Redactor::flatten(Redactor::payload($values, $sensitive));

        // A catalogued event has a known set of slots: one the caller left out renders
        // as empty (and its line is dropped), never as a literal "{{ due_date }}".
        foreach ($catalogued['data'] ?? [] as $key) {
            $full[$key] ??= '';
            $preview[$key] ??= '';
        }

        $tidy = $catalogued !== null;
        $fullBody = $template->render($full, $tidy);
        $previewBody = $template->render($preview, $tidy);
        $notificationType = $catalogued['notification_type'] ?? $data['notification_type'] ?? 'transactional';
        $recipient = $data['recipient']['email'] ?? $data['recipient']['user_email'];

        $log = DB::transaction(function () use ($event, $team, $rule, $channel, $template, $data, $recipient, $fullBody, $previewBody, $preview, $notificationType) {
            $log = NotifyLog::withoutGlobalScopes()->create([
                'team_id' => $team->id,
                'channel_id' => $channel->id,
                'template_id' => $template->id,
                'rule_id' => $rule->id,
                'recipient' => $recipient,
                // The stored subject uses the masked values; a secret never lands here in plain text.
                'subject' => $template->renderSubject($preview) ?: null,
                'body_preview' => $previewBody,
                // Keep the real text (encrypted) only when the preview had secrets masked.
                'body' => $fullBody !== $previewBody ? $fullBody : null,
                'notification_type' => $notificationType,
                'event' => $data['event'],
                'status' => 'queued',
            ]);

            $event->update(['status' => 'routed', 'notify_log_id' => $log->id]);

            return $log;
        });

        try {
            DeliverNotifyLog::dispatch($log->id);
        } catch (Throwable $e) {
            // Queue unavailable: the log is safely 'queued' and the sweeper will enqueue it.
            Log::warning('Ingest could not enqueue delivery; the sweeper will pick it up.', ['notify_log_id' => $log->id]);
        }
    }

    private function finish(NotifyInboundEvent $event, string $status, string $note): void
    {
        $event->update(['status' => $status, 'note' => $note]);
    }
}
