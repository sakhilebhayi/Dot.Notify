<?php

namespace Tests\Feature\Notify;

use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\User;
use App\Services\NotifyDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotifyDispatcherInAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_queued_in_app_log_notifies_the_matching_team_member(): void
    {
        $recipientUser = User::factory()->withPersonalTeam()->create(['email' => 'member@example.com']);
        $team = $recipientUser->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'in_app',
            'name' => 'In-App',
        ]);

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => 'member@example.com',
            'subject' => 'Test subject',
            'body_preview' => 'Test body.',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        $log->refresh();
        $this->assertSame('sent', $log->status);
        $this->assertSame(1, $recipientUser->fresh()->unreadNotifications()->count());
    }

    public function test_an_in_app_log_with_no_matching_user_fails_cleanly(): void
    {
        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'in_app',
            'name' => 'In-App',
        ]);

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => 'nobody@example.com',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame('No matching user found for in-app delivery.', $log->failure_reason);
    }

    public function test_an_in_app_log_does_not_match_a_user_from_a_different_team(): void
    {
        $outsider = User::factory()->withPersonalTeam()->create(['email' => 'outsider@example.com']);
        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'in_app',
            'name' => 'In-App',
        ]);

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => 'outsider@example.com',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame(0, $outsider->fresh()->unreadNotifications()->count());
    }
}
