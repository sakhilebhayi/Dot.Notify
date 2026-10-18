<?php

namespace App\Livewire\Notify;

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * In-app notification bell for Dot.Notify's own operators (platform-loop
 * pass, 2026-08-01) — surfaces ChannelDegradedNotification,
 * BatchFailedNotification, and QueuedNotificationDelivered records via
 * Laravel's `database` notification channel. See NotifyChannel::booted() /
 * NotifyBatch::booted() / NotifyDispatcher for what dispatches these.
 *
 * Updates live: those three notification classes also broadcast over
 * Reverb (the 'broadcast' channel in their via()), and
 * resources/js/notifications.js listens on the current user's private
 * channel and dispatches 'notification-received' below.
 */
class NotificationBell extends Component
{
    public bool $open = false;

    #[Computed]
    public function notifications(): Collection
    {
        return auth()->user()->notifications()->latest()->limit(10)->get();
    }

    #[Computed]
    public function unreadCount(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function markAsRead(string $notificationId): void
    {
        auth()->user()->notifications()->where('id', $notificationId)->first()?->markAsRead();
        unset($this->notifications, $this->unreadCount);
    }

    #[On('notifications-refresh')]
    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
        unset($this->notifications, $this->unreadCount);
    }

    /**
     * A new notification arrived over the WebSocket -- bust the cached
     * computed properties so the next render picks it up. Deliberately
     * distinct from markAllAsRead() above: receiving a new notification
     * must not mark existing ones as read.
     */
    #[On('notification-received')]
    public function refresh(): void
    {
        unset($this->notifications, $this->unreadCount);
    }

    public function render()
    {
        return view('livewire.notify.notification-bell');
    }
}
