<?php

namespace Tests\Feature\Notify;

use App\Livewire\Notify\NotificationBell;
use App\Models\NotifyBatch;
use App\Models\NotifyChannel;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the in-app self-notifications added for Dot.Notify's own operators
 * (platform-loop pass): ChannelDegradedNotification fires when a channel's
 * test_status transitions to "failed" (NotifyChannel::booted()) and
 * BatchFailedNotification fires when a batch's status transitions to
 * "failed" (NotifyBatch::booted()). Both are dispatched to every member of
 * the owning team via the `database` notification channel.
 */
class NotifySelfNotificationTest extends TestCase
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

    public function test_channel_failing_its_test_notifies_the_team(): void
    {
        $channel = NotifyChannel::create([
            'team_id'     => $this->team->id,
            'type'        => 'email',
            'name'        => 'Primary Email',
            'test_status' => 'ok',
        ]);

        $channel->update(['test_status' => 'failed']);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertEquals(1, $this->user->fresh()->unreadNotifications()->count());
    }

    public function test_channel_created_directly_with_failed_status_does_not_notify(): void
    {
        // Observer only fires on the transition (update), not on create,
        // so seeding a channel that starts out failed shouldn't notify.
        NotifyChannel::create([
            'team_id'     => $this->team->id,
            'type'        => 'email',
            'name'        => 'Broken From Day One',
            'test_status' => 'failed',
        ]);

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_channel_staying_ok_does_not_notify(): void
    {
        $channel = NotifyChannel::create([
            'team_id'     => $this->team->id,
            'type'        => 'email',
            'name'        => 'Healthy',
            'test_status' => 'ok',
        ]);

        $channel->update(['name' => 'Healthy (renamed)']);

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_batch_failing_notifies_the_team(): void
    {
        $batch = NotifyBatch::create([
            'team_id'          => $this->team->id,
            'name'             => 'Campaign X',
            'status'           => 'sending',
            'total_recipients' => 100,
            'sent_count'       => 40,
            'failed_count'     => 5,
        ]);

        $batch->update(['status' => 'failed']);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertEquals(1, $this->user->fresh()->unreadNotifications()->count());
    }

    public function test_notification_bell_reflects_unread_count_and_marks_read(): void
    {
        $channel = NotifyChannel::create([
            'team_id'     => $this->team->id,
            'type'        => 'sms',
            'name'        => 'SMS Gateway',
            'test_status' => 'ok',
        ]);
        $channel->update(['test_status' => 'failed']);

        Livewire::actingAs($this->user)
            ->test(NotificationBell::class)
            ->assertSet('open', false)
            ->call('toggle')
            ->assertSet('open', true)
            ->assertSee('Channel degraded')
            ->call('markAllAsRead');

        $this->assertEquals(0, $this->user->fresh()->unreadNotifications()->count());
    }
}
