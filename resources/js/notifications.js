/**
 * Real-time delivery for NotificationBell.php.
 *
 * ChannelDegradedNotification / BatchFailedNotification /
 * QueuedNotificationDelivered all use Laravel's built-in `database` +
 * `broadcast` notification channels. Echo's Channel.notification() is the
 * dedicated listener for Laravel's native
 * Illuminate\Notifications\Events\BroadcastNotificationCreated event --
 * unlike a custom domain event, there's no broadcastAs()-name mismatch to
 * get wrong here (see docs/DOT_REALTIME_STANDARD.md in Dot.Mines, the
 * pitfall that cost a whole sub-project there).
 */
function initNotifications() {
    if (typeof window.Echo === 'undefined') {
        console.error('Laravel Echo not found. Ensure bootstrap.js loaded before notifications.js.');
        return;
    }

    const userId = document.querySelector('meta[name="user-id"]')?.content;

    if (!userId) {
        // Not authenticated -- nothing to subscribe to.
        return;
    }

    window.Echo.private(`App.Models.User.${userId}`).notification(() => {
        // NotificationBell.php's #[On('notification-received')] busts its
        // #[Computed] notifications()/unreadCount() cache and re-renders.
        // Deliberately a different event name from the existing
        // 'notifications-refresh' listener, which marks everything as
        // read -- a newly arrived notification must not do that.
        window.Livewire.dispatch('notification-received');
    });
}

if (typeof window.Livewire === 'undefined') {
    document.addEventListener('livewire:init', initNotifications);
} else {
    initNotifications();
}
