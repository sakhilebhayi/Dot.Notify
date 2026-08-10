<?php

namespace App\Notifications;

use App\Models\NotifyLog;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * In-app (database channel) delivery of a queued NotifyLog whose channel
 * type is "in_app" — see App\Services\NotifyDispatcher.
 *
 * Also broadcasts over Reverb so NotificationBell.php updates live instead
 * of only on page load -- see routes/channels.php /
 * BroadcastServiceProvider for the App.Models.User.{id} channel this uses.
 */
class QueuedNotificationDelivered extends Notification
{
    public function __construct(public NotifyLog $notifyLog) {}

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
            'type' => 'notification_delivered',
            'title' => $this->notifyLog->subject ?? 'Notification',
            'message' => $this->notifyLog->body_preview ?? '',
            'notify_log_id' => $this->notifyLog->id,
            'url' => route('dashboard'),
        ];
    }
}
