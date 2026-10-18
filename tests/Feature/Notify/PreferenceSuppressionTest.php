<?php

namespace Tests\Feature\Notify;

use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\NotifyPreference;
use App\Models\Team;
use App\Models\User;
use App\Services\NotifyDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PreferenceSuppressionTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $recipient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = User::factory()->withPersonalTeam()->create()->currentTeam;
        $this->recipient = User::factory()->create(['email' => 'Amara@Example.test']);
    }

    private function log(string $channelType = 'email', ?string $notificationType = null, string $recipient = 'amara@example.test'): NotifyLog
    {
        $channel = NotifyChannel::create(['team_id' => $this->team->id, 'type' => $channelType, 'name' => ucfirst($channelType), 'config' => $channelType === 'webhook' ? ['url' => 'https://hooks.example.test/x'] : null]);

        return NotifyLog::create(['team_id' => $this->team->id, 'channel_id' => $channel->id, 'recipient' => $recipient, 'subject' => 'S', 'body_preview' => 'B', 'status' => 'queued', 'notification_type' => $notificationType]);
    }

    private function optOut(string $type, string $channel, bool $enabled = false, ?User $user = null): void
    {
        NotifyPreference::create(['user_id' => ($user ?? $this->recipient)->id, 'notification_type' => $type, 'channel_type' => $channel, 'enabled' => $enabled]);
    }

    public function test_an_opted_out_recipient_is_suppressed_with_a_reason_and_nothing_is_sent(): void
    {
        $this->optOut('transactional', 'email');
        $log = $this->log();

        app(NotifyDispatcher::class)->dispatch($log);

        $log->refresh();
        $this->assertSame('suppressed', $log->status);
        $this->assertSame('Recipient opted out of transactional notifications on the email channel.', $log->failure_reason);
        $this->assertNull($log->sent_at);
        $this->assertNull($log->next_retry_at);
        $this->assertCount(0, $this->sentMessages());
    }

    public function test_an_opt_out_for_a_different_channel_type_or_notification_type_does_not_suppress(): void
    {
        $this->optOut('transactional', 'sms');
        $this->optOut('marketing', 'email');
        $log = $this->log();

        app(NotifyDispatcher::class)->dispatch($log);

        $this->assertSame('sent', $log->fresh()->status);
        $this->assertCount(1, $this->sentMessages());
    }

    public function test_an_explicit_opt_in_and_other_peoples_opt_outs_do_not_suppress(): void
    {
        $this->optOut('transactional', 'email', enabled: true);
        $stranger = User::factory()->create();
        $this->optOut('transactional', 'email', user: $stranger);
        $log = $this->log();

        app(NotifyDispatcher::class)->dispatch($log);

        $this->assertSame('sent', $log->fresh()->status);
    }

    public function test_marketing_opt_out_suppresses_marketing_only(): void
    {
        $this->optOut('marketing', 'email');
        $marketing = $this->log(notificationType: 'marketing');
        $transactional = $this->log(notificationType: 'transactional');

        app(NotifyDispatcher::class)->dispatch($marketing);
        app(NotifyDispatcher::class)->dispatch($transactional);

        $this->assertSame('suppressed', $marketing->fresh()->status);
        $this->assertSame('sent', $transactional->fresh()->status);
    }

    public function test_security_notifications_are_never_suppressed(): void
    {
        $this->optOut('security', 'email');
        $log = $this->log(notificationType: 'security');

        app(NotifyDispatcher::class)->dispatch($log);

        $this->assertSame('sent', $log->fresh()->status);
    }

    public function test_a_recipient_with_no_account_has_no_preferences_and_is_sent(): void
    {
        $log = $this->log(recipient: 'stranger@example.test');

        app(NotifyDispatcher::class)->dispatch($log);

        $this->assertSame('sent', $log->fresh()->status);
    }

    public function test_suppression_applies_to_other_channels_too(): void
    {
        Http::fake();
        $this->optOut('transactional', 'webhook');
        $log = $this->log('webhook');

        app(NotifyDispatcher::class)->dispatch($log);

        $this->assertSame('suppressed', $log->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_the_model_helper_matches_emails_case_insensitively(): void
    {
        $this->optOut('transactional', 'email');

        $this->assertTrue(NotifyPreference::blocks('AMARA@example.TEST', 'transactional', 'email'));
        $this->assertFalse(NotifyPreference::blocks('amara@example.test', 'transactional', 'sms'));
        $this->assertFalse(NotifyPreference::blocks('nobody@example.test', 'transactional', 'email'));
        $this->assertSame(1, NotifyPreference::optedOut('transactional', 'email')->count());
    }
}
