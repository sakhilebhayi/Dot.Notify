<?php

namespace App\Notifications;

use App\Models\NotifyChannel;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * In-app (database channel) notification sent to every member of a team
 * the moment one of that team's delivery channels flips to test_status
 * "failed" — dispatched from NotifyChannel::booted() so operators find out
 * a channel is degraded without having to watch the Channels panel.
 *
 * Also broadcasts over Reverb so NotificationBell.php updates live instead
 * of only on page load -- see routes/channels.php /
 * BroadcastServiceProvider for the App.Models.User.{id} channel this uses.
 */
class ChannelDegradedNotification extends Notification
{
    public function __construct(public NotifyChannel $channel) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'channel_degraded',
            'title' => 'Channel degraded',
            'message' => "\"{$this->channel->name}\" ({$this->channel->type}) failed its last delivery test.",
            'channel_id' => $this->channel->id,
            'url' => route('dashboard'),
        ];
    }
}
