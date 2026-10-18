<?php

namespace App\Models;

use App\Services\Notify\NotifyEventCatalogue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class NotifyPreference extends Model
{
    protected $table = 'notify_preferences';

    protected $fillable = [
        'user_id',
        'notification_type',
        'channel_type',
        'event',
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
     * Category-wide rows where the user switched this notification type off on this
     * channel type (event = '': the per-event rows are separate, see blocks()).
     */
    public function scopeOptedOut(Builder $query, string $notificationType, string $channelType): Builder
    {
        return $query->where('notification_type', $notificationType)
            ->where('channel_type', $channelType)
            ->where('event', '')
            ->where('enabled', false);
    }

    /**
     * Has the person with this email opted out? Someone with no account has
     * no preferences, so they are never blocked. Email match ignores case.
     *
     * Two kinds of row can block: the whole notification type on this channel type
     * (event = ''), and, when the notification came from a catalogued event, that
     * one event on this channel type. Callers never ask about 'security'
     * notices: those are not suppressible (NotifyDispatcher checks first).
     */
    public static function blocks(string $email, string $notificationType, string $channelType, ?string $event = null): bool
    {
        return static::query()
            ->where('channel_type', $channelType)
            ->where('enabled', false)
            ->where(function (Builder $match) use ($notificationType, $event) {
                $match->where(fn (Builder $category) => $category->where('event', '')->where('notification_type', $notificationType));

                if ($event !== null && $event !== '') {
                    $match->orWhere('event', $event);
                }
            })
            ->whereIn('user_id', User::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->select('id'))
            ->exists();
    }

    /**
     * Switch one catalogued event off (or back on) for one person on one channel
     * type. Reuses the person's row for that event. A security notice cannot be
     * switched off, so asking for one is refused rather than silently stored.
     *
     * @throws InvalidArgumentException for an event that is not catalogued, or is a security notice
     */
    public static function setForEvent(User $user, string $event, string $channelType, bool $enabled): self
    {
        $definition = app(NotifyEventCatalogue::class)->find($event)
            ?? throw new InvalidArgumentException("Unknown event {$event}.");

        if ($definition['notification_type'] === 'security') {
            throw new InvalidArgumentException("{$event} is a security notice and cannot be switched off.");
        }

        return static::updateOrCreate(
            ['user_id' => $user->id, 'notification_type' => $definition['notification_type'], 'channel_type' => $channelType, 'event' => $event],
            ['enabled' => $enabled],
        );
    }
}
