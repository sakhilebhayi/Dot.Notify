<?php

namespace App\Models;

use App\Notifications\BatchFailedNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Notification;
use App\Models\Concerns\HasTeamScope;

class NotifyBatch extends Model
{
    use HasTeamScope;

    protected $table = 'notify_batches';

    protected $fillable = [
        'team_id',
        'template_id',
        'name',
        'status',
        'total_recipients',
        'sent_count',
        'failed_count',
        'scheduled_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function deliveryRate(): float
    {
        return $this->total_recipients > 0
            ? round(($this->sent_count / $this->total_recipients) * 100, 1)
            : 0;
    }

    /**
     * Self-notification (platform-loop pass, 2026-08-01): tell every member
     * of the owning team the moment a bulk send flips to "failed", so
     * operators of this platform learn about their own failed batch runs
     * in-app. Fires on update only and only on the transition into
     * "failed", so it doesn't re-notify on every save.
     */
    protected static function booted(): void
    {
        static::updated(function (self $batch) {
            if ($batch->wasChanged('status') && $batch->status === 'failed' && $batch->team) {
                Notification::send($batch->team->allUsers(), new BatchFailedNotification($batch));
            }
        });
    }
}
