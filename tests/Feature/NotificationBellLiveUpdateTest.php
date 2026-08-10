<?php

namespace Tests\Feature;

use App\Livewire\Notify\NotificationBell;
use App\Models\NotifyBatch;
use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\Team;
use App\Models\User;
use App\Notifications\BatchFailedNotification;
use App\Notifications\ChannelDegradedNotification;
use App\Notifications\QueuedNotificationDelivered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the real-time delivery added on top of NotifySelfNotificationTest's
 * existing database-channel coverage: ChannelDegradedNotification /
 * BatchFailedNotification / QueuedNotificationDelivered now also broadcast
 * over Reverb (private App.Models.User.{id} channel — see
 * BroadcastChannelAuthorizationTest for authorization), and
 * NotificationBell.php's #[On('notification-received')] picks that up.
 */
class NotificationBellLiveUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->withPersonalTeam()->create();
        $this->team = $this->user->currentTeam;
    }

    public function test_channel_degraded_notification_broadcasts(): void
    {
        $channel = NotifyChannel::create([
            'team_id' => $this->team->id,
            'type' => 'email',
            'name' => 'Primary Email',
            'test_status' => 'ok',
        ]);

        $notification = new ChannelDegradedNotification($channel);

        $this->assertContains('broadcast', $notification->via($this->user));
        $this->assertSame(
            $notification->toDatabase($this->user),
            $notification->toBroadcast($this->user)->data
        );
    }

    public function test_batch_failed_notification_broadcasts(): void
    {
        $batch = NotifyBatch::create([
            'team_id' => $this->team->id,
            'name' => 'Campaign X',
            'status' => 'sending',
            'total_recipients' => 100,
            'sent_count' => 40,
            'failed_count' => 5,
        ]);

        $notification = new BatchFailedNotification($batch);

        $this->assertContains('broadcast', $notification->via($this->user));
        $this->assertSame(
            $notification->toDatabase($this->user),
            $notification->toBroadcast($this->user)->data
        );
    }

    public function test_queued_notification_delivered_broadcasts(): void
    {
        $log = NotifyLog::create([
            'team_id' => $this->team->id,
            'recipient' => (string) $this->user->id,
            'subject' => 'Test subject',
            'body_preview' => 'Preview text',
            'status' => 'delivered',
        ]);

        $notification = new QueuedNotificationDelivered($log);

        $this->assertContains('broadcast', $notification->via($this->user));
        $this->assertSame(
            $notification->toDatabase($this->user),
            $notification->toBroadcast($this->user)->data
        );
    }

    /**
     * #[Computed] properties cache for the component's lifetime -- a
     * notification created after the bell has already rendered must not
     * appear until the 'notification-received' event (dispatched by
     * resources/js/notifications.js when Echo receives a broadcast) busts
     * that cache.
     */
    public function test_notification_received_event_refreshes_the_bell(): void
    {
        $component = Livewire::actingAs($this->user)->test(NotificationBell::class);
        $component->assertSet('unreadCount', 0);

        $channel = NotifyChannel::create([
            'team_id' => $this->team->id,
            'type' => 'sms',
            'name' => 'SMS Gateway',
            'test_status' => 'ok',
        ]);
        $channel->update(['test_status' => 'failed']);

        // Still cached at this point -- nothing has told the component to
        // recompute yet.
        $component->assertSet('unreadCount', 0);

        $component->dispatch('notification-received');

        $component->assertSet('unreadCount', 1);
    }

    /**
     * 'notification-received' must only bust the cache, never mark
     * anything read -- that's markAllAsRead()'s job on the differently
     * named 'notifications-refresh' event.
     */
    public function test_notification_received_event_does_not_mark_anything_read(): void
    {
        $channel = NotifyChannel::create([
            'team_id' => $this->team->id,
            'type' => 'sms',
            'name' => 'SMS Gateway',
            'test_status' => 'ok',
        ]);
        $channel->update(['test_status' => 'failed']);

        Livewire::actingAs($this->user)
            ->test(NotificationBell::class)
            ->dispatch('notification-received')
            ->assertSet('unreadCount', 1);

        $this->assertEquals(1, $this->user->fresh()->unreadNotifications()->count());
    }
}
