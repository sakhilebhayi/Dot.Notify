<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\BroadcastServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * App\Providers\BroadcastServiceProvider authorizes the
 * App.Models.User.{id} private channel that ChannelDegradedNotification /
 * BatchFailedNotification / QueuedNotificationDelivered broadcast on (via
 * Laravel's built-in broadcast notification channel). This exercises the
 * real /broadcasting/auth endpoint, matching the pattern established in
 * Dot.Mines (see docs/DOT_REALTIME_STANDARD.md there).
 */
class BroadcastChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml forces BROADCAST_CONNECTION=null in the test env so
        // other tests never attempt a real network call -- but the null
        // driver's auth() is a no-op that authorizes everything
        // unconditionally. Switch to a real Pusher-protocol driver for
        // this test class, and re-run channel registration afterward:
        // Broadcast::channel() registers against whichever driver instance
        // is current at call time, and the provider already booted against
        // the null driver during app bootstrap before this setUp() runs.
        config(['broadcasting.default' => 'reverb']);
        (new BroadcastServiceProvider($this->app))->boot();
    }

    private function authRequest(User $user, string $channelName): TestResponse
    {
        return $this->actingAs($user)->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => $channelName,
        ]);
    }

    public function test_user_can_authorize_their_own_channel(): void
    {
        $user = User::factory()->create();

        $this->authRequest($user, "private-App.Models.User.{$user->id}")
            ->assertOk();
    }

    public function test_user_cannot_authorize_another_users_channel(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->authRequest($user, "private-App.Models.User.{$otherUser->id}")
            ->assertForbidden();
    }

    public function test_non_numeric_identifier_fails_closed_rather_than_erroring(): void
    {
        $user = User::factory()->create();

        $this->authRequest($user, 'private-App.Models.User.not-a-number')
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_authorize_any_private_channel(): void
    {
        $user = User::factory()->create();

        $this->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-App.Models.User.{$user->id}",
        ])->assertForbidden();
    }
}
