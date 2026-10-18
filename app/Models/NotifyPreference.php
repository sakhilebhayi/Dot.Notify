<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotifyPreference extends Model
{
    protected $table = 'notify_preferences';

    protected $fillable = [
        'user_id',
        'notification_type',
        'channel_type',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Rows where the user switched this notification type off on this channel type.
     */
    public function scopeOptedOut(Builder $query, string $notificationType, string $channelType): Builder
    {
        return $query->where('notification_type', $notificationType)
            ->where('channel_type', $channelType)
            ->where('enabled', false);
    }

    /**
     * Has the person with this email opted out? Someone with no account has
     * no preferences, so they are never blocked. Email match ignores case.
     */
    public static function blocks(string $email, string $notificationType, string $channelType): bool
    {
        return static::query()
            ->optedOut($notificationType, $channelType)
            ->whereIn('user_id', User::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->select('id'))
            ->exists();
    }
}
