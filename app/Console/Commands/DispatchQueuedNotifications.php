<?php

namespace App\Console\Commands;

use App\Models\NotifyLog;
use App\Services\NotifyDispatcher;
use Illuminate\Console\Command;

/**
 * This platform's first scheduled job (Dot.Brain audit's own named Level
 * 1 gap, dot-notify.md). Dispatches every queued NotifyLog to its real
 * channel driver via App\Services\NotifyDispatcher — see
 * docs/superpowers/specs/2026-08-09-queued-notification-dispatch-design.md.
 *
 * No Level 2 gate: the human approval already happened when the team
 * configured the NotifyRule and activated the NotifyChannel that produced
 * this queued log.
 */
class DispatchQueuedNotifications extends Command
{
    protected $signature = 'notify:dispatch-queued';

    protected $description = 'Dispatch every queued NotifyLog to its channel driver.';

    public function handle(NotifyDispatcher $dispatcher): int
    {
        NotifyLog::where('status', 'queued')->get()->each(
            fn (NotifyLog $log) => $dispatcher->dispatch($log)
        );

        $this->info('Queued notification dispatch complete.');

        return self::SUCCESS;
    }
}
