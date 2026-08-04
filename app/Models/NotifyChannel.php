<?php

namespace App\Models;

use App\Notifications\ChannelDegradedNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Notification;
use App\Models\Concerns\HasTeamScope;

class NotifyChannel extends Model
{
    use HasTeamScope;

    protected $table = 'notify_channels';

    protected $fillable = [
        'team_id',
        'type',
        'name',
        'config',
        'is_active',
        'last_tested_at',
        'test_status',
    ];

    protected $casts = [
        'config'         => 'array',
        'is_active'      => 'boolean',
        'last_tested_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isHealthy(): bool
    {
        return $this->is_active && $this->test_status === 'ok';
    }

    /**
     * Self-notification (platform-loop pass, 2026-08-01): tell every member
     * of the owning team the moment a channel's test flips to "failed", so
     * operators of this platform learn about their own delivery outages
     * in-app instead of only discovering them via the dashboard's failure
     * count. Fires on update only (not on the initial create) and only on
     * the transition into "failed", so it doesn't re-notify on every save.
     */
    protected static function booted(): void
    {
        static::updated(function (self $channel) {
            if ($channel->wasChanged('test_status') && $channel->test_status === 'failed' && $channel->team) {
                Notification::send($channel->team->allUsers(), new ChannelDegradedNotification($channel));
            }
        });
    }
}
