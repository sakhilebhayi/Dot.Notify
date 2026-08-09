<?php

namespace App\Notifications;

use App\Models\NotifyLog;
use Illuminate\Notifications\Notification;

/**
 * In-app (database channel) delivery of a queued NotifyLog whose channel
 * type is "in_app" — see App\Services\NotifyDispatcher.
 */
class QueuedNotificationDelivered extends Notification
{
    public function __construct(public NotifyLog $notifyLog) {}

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
            'type' => 'notification_delivered',
            'title' => $this->notifyLog->subject ?? 'Notification',
            'message' => $this->notifyLog->body_preview ?? '',
            'notify_log_id' => $this->notifyLog->id,
            'url' => route('dashboard'),
        ];
    }
}
