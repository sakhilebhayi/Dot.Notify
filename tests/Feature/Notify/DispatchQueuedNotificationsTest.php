<?php

namespace Tests\Feature\Notify;

use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DispatchQueuedNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_queued_logs_are_dispatched(): void
    {
        Mail::shouldReceive('raw')->once()->andReturnNull();

        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'email',
            'name' => 'Primary Email',
        ]);

        $queuedLog = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => 'someone@example.com',
            'subject' => 'Queued',
            'body_preview' => 'Body.',
            'status' => 'queued',
        ]);

        $alreadySentLog = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => 'someone-else@example.com',
            'subject' => 'Already sent',
            'body_preview' => 'Body.',
            'status' => 'sent',
            'sent_at' => now()->subHour(),
        ]);

        $this->artisan('notify:dispatch-queued')->assertExitCode(0);

        $this->assertSame('sent', $queuedLog->fresh()->status);
        // Untouched — its sent_at must not move.
        $this->assertEquals($alreadySentLog->sent_at->timestamp, $alreadySentLog->fresh()->sent_at->timestamp);
    }

    public function test_multiple_queued_logs_across_channel_types_are_each_dispatched_independently(): void
    {
        Mail::shouldReceive('raw')->once()->andReturnNull();

        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $emailChannel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'email',
            'name' => 'Primary Email',
        ]);

        $smsChannel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'sms',
            'name' => 'SMS',
        ]);

        $emailLog = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $emailChannel->id,
            'recipient' => 'someone@example.com',
            'subject' => 'Queued',
            'body_preview' => 'Body.',
            'status' => 'queued',
        ]);

        $smsLog = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $smsChannel->id,
            'recipient' => '+15555550100',
            'status' => 'queued',
        ]);

        $this->artisan('notify:dispatch-queued');

        $this->assertSame('sent', $emailLog->fresh()->status);
        $this->assertSame('failed', $smsLog->fresh()->status);
        $this->assertSame('SMS delivery has no configured provider.', $smsLog->fresh()->failure_reason);
    }
}
