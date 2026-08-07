<?php

namespace App\Notifications;

use App\Models\NotifyBatch;
use Illuminate\Notifications\Notification;

/**
 * In-app (database channel) notification sent to every member of a team
 * the moment one of that team's bulk sends flips to status "failed" —
 * dispatched from NotifyBatch::booted() so operators find out a batch
 * send died without having to watch the Batches list.
 */
class BatchFailedNotification extends Notification
{
    public function __construct(public NotifyBatch $batch) {}

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
            'type' => 'batch_failed',
            'title' => 'Batch send failed',
            'message' => "\"{$this->batch->name}\" failed after sending {$this->batch->sent_count} of {$this->batch->total_recipients} recipients ({$this->batch->failed_count} failures).",
            'batch_id' => $this->batch->id,
            'url' => route('dashboard'),
        ];
    }
}
