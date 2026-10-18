<?php

namespace App\Console\Commands;

use App\Jobs\DeliverNotifyLog;
use App\Models\NotifyLog;
use Illuminate\Console\Command;

/**
 * Thin sweeper, run every minute. It never sends anything itself:
 *
 *  1. Rows stuck in "sending" past the stale window (a worker died mid-send)
 *     are marked failed with an honest "outcome unknown" reason. They are NOT
 *     re-sent, because the send may have gone through.
 *  2. At most --limit due logs (queued, or retrying whose next_retry_at has
 *     passed) are handed to the queue as DeliverNotifyLog jobs, oldest first.
 *
 * It is a safety net: the ingest endpoint and each retry already enqueue
 * their own jobs. Delivery needs a running queue worker.
 */
class DispatchQueuedNotifications extends Command
{
    protected $signature = 'notify:dispatch-queued {--limit= : Most logs to enqueue this run (default notify.delivery.sweep_batch)}';

    protected $description = 'Enqueue due queued/retrying NotifyLogs for delivery (bounded; never sends inline).';

    public function handle(): int
    {
        $limit = max(1, (int) ($this->option('limit') ?: config('notify.delivery.sweep_batch', 100)));

        $reaped = NotifyLog::withoutGlobalScopes()
            ->where('status', 'sending')
            ->where('last_attempt_at', '<', now()->subMinutes((int) config('notify.delivery.stale_sending_minutes', 15)))
            ->update([
                'status' => 'failed',
                'failure_reason' => 'Delivery outcome unknown: a worker stopped mid-send. Not retried automatically so the recipient is not sent it twice; check the destination before re-queuing.',
                'updated_at' => now(),
            ]);

        $ids = NotifyLog::withoutGlobalScopes()
            ->whereIn('status', ['queued', 'retrying'])
            ->where(fn ($q) => $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now()))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $id) {
            DeliverNotifyLog::dispatch($id);
        }

        $this->info("Queued notification sweep complete: {$ids->count()} enqueued, {$reaped} stuck row(s) failed.");

        return self::SUCCESS;
    }
}
