<?php

namespace App\Services;

use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\User;
use App\Notifications\QueuedNotificationDelivered;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Dispatches a queued NotifyLog to its channel's real driver — this
 * platform's own named Level 1 gap (Dot.Brain audit, dot-notify.md,
 * 2026-08-08). See
 * docs/superpowers/specs/2026-08-09-queued-notification-dispatch-design.md.
 *
 * Every failure path sets a specific, honest failure_reason — never a
 * generic message, never a fabricated success.
 */
class NotifyDispatcher
{
    public function dispatch(NotifyLog $log): void
    {
        $channel = $log->channel;

        if (! $channel) {
            $this->fail($log, 'No channel configured for this notification.');

            return;
        }

        match ($channel->type) {
            'email' => $this->dispatchEmail($log),
            'in_app' => $this->dispatchInApp($log, $channel),
            'sms' => $this->fail($log, 'SMS delivery has no configured provider.'),
            'push' => $this->fail($log, 'Push delivery has no configured provider.'),
            default => $this->fail($log, 'Unknown channel type.'),
        };
    }

    private function dispatchInApp(NotifyLog $log, NotifyChannel $channel): void
    {
        $team = $channel->team;
        $user = $team ? User::where('email', $log->recipient)->first() : null;

        if (! $user || ! $user->belongsToTeam($team)) {
            $this->fail($log, 'No matching user found for in-app delivery.');

            return;
        }

        $user->notify(new QueuedNotificationDelivered($log));

        $this->succeed($log);
    }

    private function dispatchEmail(NotifyLog $log): void
    {
        try {
            Mail::raw($log->body_preview ?? '', function (Message $message) use ($log) {
                $message->to($log->recipient)->subject($log->subject ?? 'Notification');
            });

            $this->succeed($log);
        } catch (Throwable $e) {
            $this->fail($log, $e->getMessage());
        }
    }

    private function succeed(NotifyLog $log): void
    {
        $log->update(['status' => 'sent', 'sent_at' => now()]);
    }

    private function fail(NotifyLog $log, string $reason): void
    {
        $log->update(['status' => 'failed', 'failure_reason' => $reason]);
    }
}
