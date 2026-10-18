<?php

namespace App\Jobs;

use App\Models\NotifyLog;
use App\Services\NotifyDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Delivers one NotifyLog. Requires a running queue worker
 * (php artisan queue:work); the every-minute sweeper only enqueues these.
 *
 * Unique per log id until a worker starts it, so the sweeper and the ingest
 * endpoint cannot pile duplicates onto the queue. That is only an
 * optimisation: correctness comes from the row claim inside
 * NotifyDispatcher::dispatch(), which makes a duplicate job a no-op.
 *
 * Transient failures are rescheduled here with the log's own backoff
 * (30s, 2m, 8m, 32m), up to 5 attempts total. $tries / backoff() only
 * govern unexpected crashes of the job itself.
 */
class DeliverNotifyLog implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $logId) {}

    public function uniqueId(): string
    {
        return (string) $this->logId;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return NotifyDispatcher::BACKOFF;
    }

    public function handle(NotifyDispatcher $dispatcher): void
    {
        $log = NotifyLog::withoutGlobalScopes()->find($this->logId);

        if (! $log) {
            return;
        }

        $dispatcher->dispatch($log);

        $log->refresh();

        if ($log->status === 'retrying') {
            static::dispatch($this->logId)->delay(NotifyDispatcher::backoffSeconds($log->attempts));
        }
    }
}
