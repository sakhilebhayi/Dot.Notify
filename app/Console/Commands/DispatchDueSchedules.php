<?php

namespace App\Console\Commands;

use App\Models\NotifyLog;
use App\Models\NotifySchedule;
use Illuminate\Console\Command;

/**
 * The other half of the notify_schedules gap (see App\Models\NotifySchedule's
 * own doc comment): this is what actually decides "due" and turns it into
 * real NotifyLog rows, the same queued-status entry point every other send
 * path in this app produces (see WebhookInboundController, this platform's
 * proven precedent for creating a NotifyLog outside a direct user action).
 *
 * A schedule fires on a timer, not a domain event, so it has no triggering
 * payload to pull a recipient from — recipients is the schedule's own
 * explicit list.
 */
class DispatchDueSchedules extends Command
{
    protected $signature = 'notify:dispatch-schedules';

    protected $description = 'Create queued NotifyLog rows for every NotifySchedule due this minute.';

    public function handle(): int
    {
        $now = now();
        $dispatchedSchedules = 0;
        $createdLogs = 0;

        NotifySchedule::where('is_active', true)
            ->whereHas('rule', fn ($query) => $query->where('is_active', true))
            ->with('rule.template', 'rule.channel')
            ->get()
            ->each(function (NotifySchedule $schedule) use ($now, &$dispatchedSchedules, &$createdLogs) {
                if ($schedule->alreadyRanThisMinute($now) || ! $schedule->isDueAt($now)) {
                    return;
                }

                $rule = $schedule->rule;

                foreach ($schedule->recipients ?? [] as $recipient) {
                    NotifyLog::create([
                        'team_id' => $schedule->team_id,
                        'channel_id' => $rule->channel_id,
                        'template_id' => $rule->template_id,
                        'recipient' => $recipient,
                        'subject' => $rule->template?->subject,
                        'body_preview' => $rule->template?->render(),
                        'status' => 'queued',
                    ]);
                    $createdLogs++;
                }

                $schedule->update([
                    'last_run_at' => $now,
                    'next_run_at' => $schedule->nextRunAfter($now),
                ]);
                $dispatchedSchedules++;
            });

        $this->info("Schedule dispatch complete: {$dispatchedSchedules} schedule(s) ran, {$createdLogs} notification(s) queued.");

        return self::SUCCESS;
    }
}
