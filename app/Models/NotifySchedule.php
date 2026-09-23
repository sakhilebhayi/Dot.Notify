<?php

namespace App\Models;

use App\Models\Concerns\HasTeamScope;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Recurring sends tied to a NotifyRule via cron_expression + timezone —
 * README/wiki.md §6's named gap: the notify_schedules table has existed
 * since the platform's first migration with nothing reading or writing
 * it. Evaluated by App\Console\Commands\DispatchDueSchedules, which is
 * where "due" actually gets decided; the methods here are the reusable
 * cron-math this platform didn't have before.
 */
class NotifySchedule extends Model
{
    use HasFactory, HasTeamScope;

    protected $table = 'notify_schedules';

    protected $fillable = [
        'team_id',
        'rule_id',
        'name',
        'cron_expression',
        'timezone',
        'recipients',
        'is_active',
        'last_run_at',
        'next_run_at',
    ];

    protected $casts = [
        'recipients' => 'array',
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(NotifyRule::class, 'rule_id');
    }

    public function isDueAt(Carbon $now): bool
    {
        return (new CronExpression($this->cron_expression))->isDue($now->copy()->toDateTime(), $this->timezone ?: 'UTC');
    }

    /**
     * Guards against dispatching twice for the same matching minute if the
     * scheduler command is ever re-run within it — mirrors
     * DispatchQueuedNotifications' ->withoutOverlapping(), which only
     * guards a single process, not a manual re-run.
     */
    public function alreadyRanThisMinute(Carbon $now): bool
    {
        return $this->last_run_at !== null
            && $this->last_run_at->greaterThanOrEqualTo($now->copy()->startOfMinute());
    }

    public function nextRunAfter(Carbon $now): Carbon
    {
        return Carbon::instance(
            (new CronExpression($this->cron_expression))->getNextRunDate($now->copy()->toDateTime(), 0, false, $this->timezone ?: 'UTC')
        );
    }
}
