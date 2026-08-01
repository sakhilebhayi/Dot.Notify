<?php

namespace Tests\Feature\Notify;

use App\Models\NotifyChannel;
use App\Models\NotifyInboundEvent;
use App\Models\NotifyLog;
use App\Models\NotifyRule;
use App\Models\NotifyTemplate;
use App\Models\NotifyWebhook;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the inbound webhook endpoint (POST /webhooks/{token}) added to
 * close the gap flagged in wiki.md: NotifyWebhook issued tokens but nothing
 * ever verified a request against them. This is the highest-value test
 * file in this pass since it's a real, unauthenticated security boundary.
 */
class WebhookInboundTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private NotifyWebhook $webhook;

    protected function setUp(): void
    {
        parent::setUp();

        $user       = User::factory()->withPersonalTeam()->create();
        $this->team = $user->currentTeam;

        $this->webhook = NotifyWebhook::create([
            'team_id' => $this->team->id,
            'name'    => 'Stripe Events',
            'source'  => 'Stripe',
            'event_map' => [
                'payment.failed' => 'payment.failed',
            ],
        ]);
    }

    private function sign(array $payload, ?string $secret = null): array
    {
        $body      = json_encode($payload);
        $secret    = $secret ?? $this->webhook->signing_secret;
        $signature = hash_hmac('sha256', $body, $secret);

        return [$body, $signature];
    }

    public function test_valid_signature_is_accepted_and_recorded(): void
    {
        $payload = ['event' => 'payment.failed', 'recipient' => 'ops@example.com'];
        [, $signature] = $this->sign($payload);

        $response = $this->postJson(
            "/webhooks/{$this->webhook->endpoint_token}",
            $payload,
            ['X-Dot-Signature' => $signature]
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('notify_inbound_events', [
            'notify_webhook_id' => $this->webhook->id,
            'source_event'      => 'payment.failed',
        ]);

        $event = NotifyInboundEvent::first();
        $this->assertNotNull($event->verified_at);
        // No NotifyRule exists for "payment.failed" in this test, so the
        // event is recorded and verified but not routed anywhere.
        $this->assertEquals('ignored', $event->status);
    }

    public function test_valid_signature_with_matching_rule_creates_notify_log(): void
    {
        $channel  = NotifyChannel::create(['team_id' => $this->team->id, 'type' => 'email', 'name' => 'Ops Email']);
        $template = NotifyTemplate::create([
            'team_id'      => $this->team->id,
            'name'         => 'Payment Failed',
            'body'         => 'Payment failed for {{ recipient }}',
            'channel_type' => 'email',
        ]);
        NotifyRule::create([
            'team_id'       => $this->team->id,
            'template_id'   => $template->id,
            'channel_id'    => $channel->id,
            'trigger_event' => 'payment.failed',
            'is_active'     => true,
        ]);

        $payload = ['event' => 'payment.failed', 'recipient' => 'ops@example.com'];
        [, $signature] = $this->sign($payload);

        $this->postJson(
            "/webhooks/{$this->webhook->endpoint_token}",
            $payload,
            ['X-Dot-Signature' => $signature]
        )->assertStatus(200);

        $this->assertDatabaseHas('notify_logs', [
            'team_id'   => $this->team->id,
            'recipient' => 'ops@example.com',
            'status'    => 'queued',
        ]);

        $event = NotifyInboundEvent::first();
        $this->assertEquals('routed', $event->status);
        $this->assertNotNull($event->notify_log_id);
    }

    public function test_invalid_signature_is_rejected_with_generic_401(): void
    {
        $payload = ['event' => 'payment.failed'];
        [$body] = $this->sign($payload);

        $response = $this->postJson(
            "/webhooks/{$this->webhook->endpoint_token}",
            $payload,
            ['X-Dot-Signature' => 'deadbeef' . hash_hmac('sha256', $body, 'wrong-secret')]
        );

        $response->assertStatus(401);
        $this->assertDatabaseCount('notify_inbound_events', 0);
        $this->assertDatabaseCount('notify_logs', 0);
    }

    public function test_missing_signature_is_rejected(): void
    {
        $response = $this->postJson(
            "/webhooks/{$this->webhook->endpoint_token}",
            ['event' => 'payment.failed']
        );

        $response->assertStatus(401);
        $this->assertDatabaseCount('notify_inbound_events', 0);
    }

    public function test_unknown_token_returns_same_generic_response_as_invalid_signature(): void
    {
        $payload = ['event' => 'payment.failed'];
        [$body, $validSignatureForRealWebhook] = $this->sign($payload);

        $unknownTokenResponse = $this->postJson(
            '/webhooks/this-token-does-not-exist',
            $payload,
            ['X-Dot-Signature' => $validSignatureForRealWebhook]
        );

        $badSignatureResponse = $this->postJson(
            "/webhooks/{$this->webhook->endpoint_token}",
            $payload,
            ['X-Dot-Signature' => hash_hmac('sha256', $body, 'not-the-real-secret')]
        );

        $unknownTokenResponse->assertStatus(401);
        $badSignatureResponse->assertStatus(401);
        $this->assertEquals(
            $badSignatureResponse->json(),
            $unknownTokenResponse->json(),
            'An unknown token must be indistinguishable from a known token with a bad signature.'
        );
        $this->assertDatabaseCount('notify_inbound_events', 0);
    }

    public function test_inactive_webhook_is_rejected_like_an_invalid_signature(): void
    {
        $this->webhook->update(['is_active' => false]);

        $payload = ['event' => 'payment.failed'];
        [, $signature] = $this->sign($payload);

        $this->postJson(
            "/webhooks/{$this->webhook->endpoint_token}",
            $payload,
            ['X-Dot-Signature' => $signature]
        )->assertStatus(401);
    }

    public function test_endpoint_is_rate_limited(): void
    {
        $payload = ['event' => 'payment.failed'];
        [, $signature] = $this->sign($payload);

        for ($i = 0; $i < 30; $i++) {
            $this->postJson(
                "/webhooks/{$this->webhook->endpoint_token}",
                $payload,
                ['X-Dot-Signature' => $signature]
            )->assertStatus(200);
        }

        $this->postJson(
            "/webhooks/{$this->webhook->endpoint_token}",
            $payload,
            ['X-Dot-Signature' => $signature]
        )->assertStatus(429);
    }
}
