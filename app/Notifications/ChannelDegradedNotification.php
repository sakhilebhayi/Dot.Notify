<?php

namespace App\Notifications;

use App\Models\NotifyChannel;
use Illuminate\Notifications\Notification;

/**
 * In-app (database channel) notification sent to every member of a team
 * the moment one of that team's delivery channels flips to test_status
 * "failed" — dispatched from NotifyChannel::booted() so operators find out
 * a channel is degraded without having to watch the Channels panel.
 */
class ChannelDegradedNotification extends Notification
{
    public function __construct(public NotifyChannel $channel)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type'       => 'channel_degraded',
            'title'      => 'Channel degraded',
            'message'    => "\"{$this->channel->name}\" ({$this->channel->type}) failed its last delivery test.",
            'channel_id' => $this->channel->id,
            'url'        => route('dashboard'),
        ];
    }
}
