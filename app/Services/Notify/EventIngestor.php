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
 * active channels. Rules are never created here.
 */
class EventIngestor
{
    /**
     * @param  array<string, mixed>  $data  validated request payload
     * @return array{0: NotifyInboundEvent, 1: bool} the event and whether it was a replay
     *
     * @throws UnknownChannel
     */
    public function ingest(Team $team, array $data): array
    {
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
                'payload' => Redactor::payload($data, array_map(fn (string $path) => 'data.'.$path, $sensitive)),
                'source_event' => $data['event'],
                'trigger_event' => $data['event'],
                'verified_at' => now(),
                'status' => 'received',
            ]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent request with the same key won the race.
            return [$this->find($team, $data), true];
        }

        $this->route($event, $team, $data, $override, $sensitive);

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
     */
    private function route(NotifyInboundEvent $event, Team $team, array $data, ?NotifyChannel $override, array $sensitive): void
    {
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
        $fullBody = $template->render(Redactor::flatten($values));
        $previewBody = $template->render(Redactor::flatten(Redactor::payload($values, $sensitive)));
        $recipient = $data['recipient']['email'] ?? $data['recipient']['user_email'];

        $log = DB::transaction(function () use ($event, $team, $rule, $channel, $template, $data, $recipient, $fullBody, $previewBody) {
            $log = NotifyLog::withoutGlobalScopes()->create([
                'team_id' => $team->id,
                'channel_id' => $channel->id,
                'template_id' => $template->id,
                'rule_id' => $rule->id,
                'recipient' => $recipient,
                'subject' => $template->subject,
                'body_preview' => $previewBody,
                // Keep the real text (encrypted) only when the preview had secrets masked.
                'body' => $fullBody !== $previewBody ? $fullBody : null,
                'notification_type' => $data['notification_type'] ?? 'transactional',
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
