<?php

namespace App\Services;

use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\NotifyPreference;
use App\Models\User;
use App\Notifications\QueuedNotificationDelivered;
use App\Services\Notify\PermanentDeliveryFailure;
use App\Services\Notify\Redactor;
use App\Services\Notify\SafeHttp;
use App\Services\Notify\TransientDeliveryFailure;
use App\Services\Notify\UnsafeUrl;
use Illuminate\Http\Client\Response;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Runs ONE delivery attempt of a NotifyLog against its channel's real driver.
 * Normally called by App\Jobs\DeliverNotifyLog; see
 * docs/superpowers/specs/2026-08-09-queued-notification-dispatch-design.md for
 * the original design and wiki.md for the queue/retry model.
 *
 * Safety properties:
 *  - The row is claimed with a single UPDATE ... WHERE status IN (queued,
 *    retrying) AND due. Only the worker whose UPDATE touched the row sends, so
 *    a duplicate job, a second worker or a stale in-memory copy cannot send twice.
 *  - A failure is classified: PermanentDeliveryFailure (4xx, bad address,
 *    blocked URL) fails at once; anything else is transient and retried with
 *    exponential backoff until max_attempts.
 *  - Every failure path stores a specific reason, with secret URLs scrubbed.
 *  - If a worker dies mid-send the row stays "sending"; the sweeper later marks
 *    it failed ("outcome unknown") instead of sending again.
 */
class NotifyDispatcher
{
    /** Seconds to wait after attempt 1, 2, 3, 4 (the 5th failure is final). */
    public const BACKOFF = [30, 120, 480, 1920];

    public function __construct(private readonly SafeHttp $http) {}

    public static function backoffSeconds(int $attempt): int
    {
        return self::BACKOFF[min(max($attempt, 1), count(self::BACKOFF)) - 1];
    }

    public function dispatch(NotifyLog $log): void
    {
        if (! $this->claim($log)) {
            return;
        }

        $log->refresh();

        try {
            $channel = $log->channel;

            if (! $channel) {
                throw new PermanentDeliveryFailure('No channel configured for this notification.');
            }

            if ($reason = $this->suppressionReason($log, $channel)) {
                $this->suppress($log, $reason);

                return;
            }

            $this->deliver($log, $channel);
            $this->succeed($log);
        } catch (PermanentDeliveryFailure $e) {
            $this->fail($log, $e->getMessage());
        } catch (Throwable $e) {
            $this->retryOrFail($log, Redactor::scrubError($e->getMessage()));
        }
    }

    /**
     * Atomic claim. Returns true only for the one caller whose UPDATE changed the row.
     */
    private function claim(NotifyLog $log): bool
    {
        return NotifyLog::withoutGlobalScopes()
            ->whereKey($log->getKey())
            ->whereIn('status', ['queued', 'retrying'])
            ->where(fn ($q) => $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now()))
            ->update([
                'status' => 'sending',
                'attempts' => DB::raw('attempts + 1'),
                'last_attempt_at' => now(),
                'updated_at' => now(),
            ]) === 1;
    }

    private function suppressionReason(NotifyLog $log, NotifyChannel $channel): ?string
    {
        $type = $log->notification_type ?: 'transactional';

        // Security notices (sign-in alerts, password resets) are not optional.
        if ($type === 'security') {
            return null;
        }

        if (NotifyPreference::blocks($log->recipient, $type, $channel->type)) {
            return "Recipient opted out of {$type} notifications on the {$channel->type} channel.";
        }

        if ($log->event && NotifyPreference::blocks($log->recipient, $type, $channel->type, $log->event)) {
            return "Recipient opted out of {$log->event} notifications on the {$channel->type} channel.";
        }

        return null;
    }

    /**
     * @throws PermanentDeliveryFailure|TransientDeliveryFailure|Throwable
     */
    private function deliver(NotifyLog $log, NotifyChannel $channel): void
    {
        match ($channel->type) {
            'email' => $this->deliverEmail($log),
            'in_app' => $this->deliverInApp($log, $channel),
            'webhook' => $this->deliverWebhook($log, $channel),
            'slack' => $this->deliverSlack($log, $channel),
            'sms' => throw new PermanentDeliveryFailure('SMS delivery has no configured provider.'),
            'push' => throw new PermanentDeliveryFailure('Push delivery has no configured provider.'),
            default => throw new PermanentDeliveryFailure('Unknown channel type.'),
        };
    }

    private function deliverInApp(NotifyLog $log, NotifyChannel $channel): void
    {
        $team = $channel->team;
        $user = $team ? User::where('email', $log->recipient)->first() : null;

        if (! $user || ! $user->belongsToTeam($team)) {
            throw new PermanentDeliveryFailure('No matching user found for in-app delivery.');
        }

        $user->notify(new QueuedNotificationDelivered($log));
    }

    private function deliverWebhook(NotifyLog $log, NotifyChannel $channel): void
    {
        $url = $channel->config['url'] ?? null;

        if (! $url) {
            throw new PermanentDeliveryFailure('Webhook channel has no configured URL.');
        }

        $response = $this->post($url, [
            'recipient' => $log->recipient,
            'subject' => $log->subject,
            'body' => $this->text($log),
        ], $channel->config['secret'] ?? null);

        $this->assertAccepted($response, 'Webhook');
    }

    private function deliverSlack(NotifyLog $log, NotifyChannel $channel): void
    {
        $url = $channel->config['webhook_url'] ?? null;

        if (! $url) {
            throw new PermanentDeliveryFailure('Slack channel has no configured webhook URL.');
        }

        $body = $this->text($log);
        $text = $log->subject ? "{$log->subject}: {$body}" : $body;

        $this->assertAccepted($this->post($url, ['text' => $text]), 'Slack webhook');
    }

    private function deliverEmail(NotifyLog $log): void
    {
        if (! filter_var($log->recipient, FILTER_VALIDATE_EMAIL)) {
            throw new PermanentDeliveryFailure('Invalid recipient email address.');
        }

        $body = $this->text($log);

        Mail::raw($body, function (Message $message) use ($log) {
            $message->to($log->recipient)->subject($log->subject ?? 'Notification');
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(string $url, array $payload, ?string $secret = null): Response
    {
        try {
            return $this->http->postJson($url, $payload, $secret);
        } catch (UnsafeUrl $e) {
            throw new PermanentDeliveryFailure($e->getMessage());
        }
    }

    /**
     * 2xx is sent. 3xx is never followed (permanent). 408/425/429 and 5xx are
     * transient. Every other 4xx means the destination rejected us for good.
     */
    private function assertAccepted(Response $response, string $label): void
    {
        $status = $response->status();

        if ($response->successful()) {
            return;
        }

        if ($status >= 300 && $status < 400) {
            throw new PermanentDeliveryFailure("{$label} answered with a redirect (HTTP {$status}); redirects are not followed.");
        }

        if ($status >= 500 || in_array($status, [408, 425, 429], true)) {
            throw new TransientDeliveryFailure("{$label} returned HTTP {$status}.");
        }

        throw new PermanentDeliveryFailure("{$label} returned HTTP {$status}.");
    }

    /** The real text to send: the encrypted full body when body_preview was redacted. */
    private function text(NotifyLog $log): string
    {
        return $log->body ?? $log->body_preview ?? '';
    }

    private function succeed(NotifyLog $log): void
    {
        $log->forceFill([
            'status' => 'sent',
            'sent_at' => now(),
            'next_retry_at' => null,
            'last_error' => null,
            'failure_reason' => null,
            'body' => null,
        ])->save();
    }

    private function suppress(NotifyLog $log, string $reason): void
    {
        $log->forceFill([
            'status' => 'suppressed',
            'failure_reason' => $reason,
            'next_retry_at' => null,
            'body' => null,
        ])->save();
    }

    private function retryOrFail(NotifyLog $log, string $error): void
    {
        if ($log->attempts >= (int) config('notify.delivery.max_attempts', 5)) {
            $this->fail($log, $error);

            return;
        }

        $log->forceFill([
            'status' => 'retrying',
            'last_error' => $error,
            'next_retry_at' => now()->addSeconds(self::backoffSeconds($log->attempts)),
        ])->save();
    }

    private function fail(NotifyLog $log, string $reason): void
    {
        $reason = Redactor::scrubError($reason);

        $log->forceFill([
            'status' => 'failed',
            'failure_reason' => $reason,
            'last_error' => $reason,
            'next_retry_at' => null,
            'body' => null,
        ])->save();
    }
}
