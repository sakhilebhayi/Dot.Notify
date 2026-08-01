<?php

namespace App\Livewire\Notify;

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * In-app notification bell for Dot.Notify's own operators (platform-loop
 * pass, 2026-08-01) — surfaces ChannelDegradedNotification and
 * BatchFailedNotification records via Laravel's `database` notification
 * channel. See NotifyChannel::booted() / NotifyBatch::booted() for what
 * dispatches these.
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

    public function render()
    {
        return view('livewire.notify.notification-bell');
    }
}
