<?php

namespace Tests\Feature\Notify;

use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\User;
use App\Services\NotifyDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotifyDispatcherWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_configured_webhook_channel_posts_and_is_marked_sent(): void
    {
        Http::fake(['https://example.com/hook' => Http::response(['ok' => true], 200)]);

        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'webhook',
            'name' => 'Webhook',
            'config' => ['url' => 'https://example.com/hook'],
        ]);

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => 'https://example.com/hook',
            'subject' => 'Test subject',
            'body_preview' => 'Test body.',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        Http::assertSent(fn ($request) => $request->url() === 'https://example.com/hook');

        $log->refresh();
        $this->assertSame('sent', $log->status);
    }

    public function test_an_unconfigured_webhook_channel_fails_without_attempting_a_request(): void
    {
        Http::fake();

        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'webhook',
            'name' => 'Webhook',
        ]);

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => 'n/a',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        Http::assertNothingSent();

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame('Webhook channel has no configured URL.', $log->failure_reason);
    }

    public function test_a_failing_webhook_response_is_recorded_with_the_status_code(): void
    {
        Http::fake(['https://example.com/hook' => Http::response('', 500)]);

        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'webhook',
            'name' => 'Webhook',
            'config' => ['url' => 'https://example.com/hook'],
        ]);

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => 'https://example.com/hook',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame('Webhook returned HTTP 500.', $log->failure_reason);
    }

    public function test_a_configured_slack_channel_posts_and_is_marked_sent(): void
    {
        Http::fake(['https://hooks.slack.example/abc' => Http::response(['ok' => true], 200)]);

        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'slack',
            'name' => 'Slack',
            'config' => ['webhook_url' => 'https://hooks.slack.example/abc'],
        ]);

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => '#general',
            'subject' => 'Alert',
            'body_preview' => 'Something happened.',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        Http::assertSent(fn ($request) => $request->url() === 'https://hooks.slack.example/abc'
            && $request['text'] === 'Alert: Something happened.');

        $log->refresh();
        $this->assertSame('sent', $log->status);
    }

    public function test_an_unconfigured_slack_channel_fails_without_attempting_a_request(): void
    {
        Http::fake();

        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'slack',
            'name' => 'Slack',
        ]);

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => '#general',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        Http::assertNothingSent();

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame('Slack channel has no configured webhook URL.', $log->failure_reason);
    }
}
