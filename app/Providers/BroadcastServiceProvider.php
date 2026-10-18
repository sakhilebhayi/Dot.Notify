<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Broadcast::routes() is already registered by bootstrap/app.php's
        // withRouting(channels: ...), which also requires routes/channels.php.
        // Only the private channel authorization callbacks live here.
        $this->requireChannels();
    }

    /**
     * Authenticate access to private channels.
     *
     * {id} is typed as plain (non-int) deliberately: a malformed value
     * (e.g. subscribing to "private-App.Models.User.not-a-number") would
     * otherwise throw an uncaught TypeError while PHP tries to coerce it to
     * an `int` parameter -- an unauthorized 500 instead of a clean,
     * fail-closed 403. See docs/DOT_REALTIME_STANDARD.md (Dot.Mines) §6.
     */
    protected function requireChannels(): void
    {
        /**
         * The default channel Laravel's broadcast notification channel uses
         * (Notifiable::receivesBroadcastNotificationsOn()) -- authorizes a
         * user to receive only their own broadcast notifications.
         * ChannelDegradedNotification / BatchFailedNotification /
         * QueuedNotificationDelivered all broadcast here once they add the
         * 'broadcast' channel to via().
         */
        Broadcast::channel('App.Models.User.{id}', function (User $user, $id) {
            return is_string($id) && ctype_digit($id) && $user->id === (int) $id;
        });
    }
}
