<?php

namespace Tests\Feature\Notify;

use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\User;
use App\Services\NotifyDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotifyDispatcherEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_queued_email_log_is_sent_and_marked_sent(): void
    {
        Mail::shouldReceive('raw')->once()->andReturnNull();

        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'email',
            'name' => 'Primary Email',
        ]);

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => 'someone@example.com',
            'subject' => 'Test subject',
            'body_preview' => 'Test body.',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        $log->refresh();
        $this->assertSame('sent', $log->status);
        $this->assertNotNull($log->sent_at);
    }

    public function test_a_log_with_no_channel_fails_cleanly(): void
    {
        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => null,
            'recipient' => 'someone@example.com',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame('No channel configured for this notification.', $log->failure_reason);
    }

    public function test_an_sms_log_fails_with_no_configured_provider(): void
    {
        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'sms',
            'name' => 'SMS Channel',
        ]);

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => '+15555550100',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame('SMS delivery has no configured provider.', $log->failure_reason);
    }

    public function test_a_push_log_fails_with_no_configured_provider(): void
    {
        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'push',
            'name' => 'Push Channel',
        ]);

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => 'device-token-abc',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame('Push delivery has no configured provider.', $log->failure_reason);
    }

    public function test_an_unknown_channel_type_fails_cleanly(): void
    {
        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'carrier_pigeon',
            'name' => 'Unsupported Channel',
        ]);

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => 'someone@example.com',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame('Unknown channel type.', $log->failure_reason);
    }

    public function test_a_mail_exception_is_caught_and_scheduled_for_retry(): void
    {
        Mail::shouldReceive('raw')->andThrow(new \RuntimeException('Connection refused'));

        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'email',
            'name' => 'Primary Email',
        ]);

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => 'someone@example.com',
            'subject' => 'Test subject',
            'body_preview' => 'Test body.',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        $log->refresh();
        // A transport error might clear up: retried with backoff, not failed for good.
        $this->assertSame('retrying', $log->status);
        $this->assertSame('Connection refused', $log->last_error);
        $this->assertNull($log->failure_reason);
        $this->assertNotNull($log->next_retry_at);
    }
}
