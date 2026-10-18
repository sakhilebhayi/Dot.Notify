<?php

namespace Tests\Feature\Notify;

use App\Jobs\DeliverNotifyLog;
use App\Models\NotifyChannel;
use App\Models\NotifyInboundEvent;
use App\Models\NotifyLog;
use App\Models\NotifyRule;
use App\Models\NotifyTemplate;
use App\Models\Team;
use App\Models\User;
use App\Services\NotifyDispatcher;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * POST /api/ingest/events: Dot apps (the CALLER) sign, Notify verifies.
 * Same scheme as InfoDot's other signed calls: HMAC-SHA256 over
 * "{timestamp}.{raw body}", X-InfoDot-Timestamp + X-InfoDot-Signature,
 * five minute tolerance, one secret per platform.
 */
class IngestEventTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'billing-ingest-secret-for-tests';

    private const OTHER_SECRET = 'files-ingest-secret-for-tests';

    private const ACME = '11111111-1111-4111-8111-111111111111';

    private const GLOBEX = '22222222-2222-4222-8222-222222222222';

    private Team $acme;

    private Team $globex;

    private NotifyChannel $acmeChannel;

    private NotifyChannel $globexChannel;

    protected function setUp(): void
    {
        parent::setUp();

        config(['notify.ingest.secrets' => ['billing' => self::SECRET, 'files' => self::OTHER_SECRET, 'tasks' => ''], 'notify.ingest.rate_limit_per_minute' => 100]);

        [$this->acme, $this->acmeChannel] = $this->tenant(self::ACME);
        [$this->globex, $this->globexChannel] = $this->tenant(self::GLOBEX);
    }

    /** @return array{0: Team, 1: NotifyChannel} */
    private function tenant(string $uuid, bool $withRule = true): array
    {
        $team = User::factory()->withPersonalTeam()->create()->currentTeam;
        DB::table('teams')->where('id', $team->id)->update(['infodot_team_uuid' => $uuid]);

        $channel = NotifyChannel::create(['team_id' => $team->id, 'type' => 'email', 'name' => 'Primary Email']);
        $template = NotifyTemplate::create(['team_id' => $team->id, 'name' => 'Overdue', 'subject' => 'Invoice overdue', 'body' => 'Hi {{ name }}, invoice {{ number }} is overdue.']);

        if ($withRule) {
            NotifyRule::create(['team_id' => $team->id, 'template_id' => $template->id, 'channel_id' => $channel->id, 'trigger_event' => 'invoice.reminder']);
        }

        return [$team->fresh(), $channel];
    }

    private function payload(array $override = []): array
    {
        return array_replace_recursive([
            'platform' => 'billing',
            'company_uuid' => self::ACME,
            'event' => 'invoice.reminder',
            'recipient' => ['email' => 'amara@example.test'],
            'data' => ['name' => 'Amara', 'number' => 'INV-7'],
            'idempotency_key' => 'inv-7-overdue-1',
        ], $override);
    }

    private function signedPost(array|string $body, array $opts = []): TestResponse
    {
        $raw = is_string($body) ? $body : json_encode($body);
        $timestamp = $opts['timestamp'] ?? now()->timestamp;
        $signature = $opts['signature'] ?? 'sha256='.hash_hmac('sha256', $timestamp.'.'.$raw, $opts['secret'] ?? self::SECRET);

        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if (($opts['headers'] ?? true) === true) {
            $server['HTTP_X_INFODOT_TIMESTAMP'] = $timestamp;
            $server['HTTP_X_INFODOT_SIGNATURE'] = $signature;
        }

        return $this->call('POST', '/api/ingest/events', [], [], [], $server, $raw);
    }

    public function test_a_valid_signed_event_is_accepted_queued_and_dispatched_as_a_job(): void
    {
        Queue::fake();

        $response = $this->signedPost($this->payload());

        $response->assertStatus(202)->assertJsonStructure(['id', 'status']);
        $event = NotifyInboundEvent::withoutGlobalScopes()->findOrFail($response->json('id'));
        $this->assertSame('routed', $response->json('status'));
        $this->assertSame($this->acme->id, $event->team_id);
        $this->assertSame('billing', $event->platform);
        $this->assertSame('inv-7-overdue-1', $event->idempotency_key);

        $log = NotifyLog::withoutGlobalScopes()->findOrFail($event->notify_log_id);
        $this->assertSame('queued', $log->status);
        $this->assertSame($this->acme->id, $log->team_id);
        $this->assertSame('amara@example.test', $log->recipient);
        $this->assertSame('Hi Amara, invoice INV-7 is overdue.', $log->body_preview);
        Queue::assertPushed(DeliverNotifyLog::class, fn (DeliverNotifyLog $job) => $job->logId === $log->id);
    }

    public function test_end_to_end_the_event_is_delivered_by_the_queued_job(): void
    {
        // QUEUE_CONNECTION=sync here, so the job runs inline; mail goes to the in-memory array transport.
        $this->signedPost($this->payload())->assertStatus(202);

        $log = NotifyLog::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('sent', $log->status);
        $this->assertSame(1, $log->attempts);
        $this->assertCount(1, $this->sentMessages());
        $this->assertSame('amara@example.test', $this->sentMessages()->first()->getEnvelope()->getRecipients()[0]->getAddress());
    }

    public function test_the_recipient_can_be_given_as_user_email(): void
    {
        Queue::fake();

        $this->signedPost($this->payload(['recipient' => ['email' => null, 'user_email' => 'bongani@example.test']]))->assertStatus(202);

        $this->assertSame('bongani@example.test', NotifyLog::withoutGlobalScopes()->firstOrFail()->recipient);
    }

    public function test_a_tampered_body_is_refused(): void
    {
        $raw = json_encode($this->payload());
        $signature = 'sha256='.hash_hmac('sha256', now()->timestamp.'.'.$raw, self::SECRET);
        $tampered = json_encode($this->payload(['recipient' => ['email' => 'attacker@example.test']]));

        $this->signedPost($tampered, ['signature' => $signature])->assertStatus(401)->assertExactJson(['error' => 'invalid_request']);
        $this->assertSame(0, NotifyLog::withoutGlobalScopes()->count());
    }

    public function test_missing_headers_wrong_secret_and_garbage_signature_all_get_the_same_401(): void
    {
        $body = $this->payload();

        $missing = $this->signedPost($body, ['headers' => false]);
        $wrong = $this->signedPost($body, ['secret' => self::OTHER_SECRET]);
        $garbage = $this->signedPost($body, ['signature' => 'sha256=deadbeef']);

        foreach ([$missing, $wrong, $garbage] as $response) {
            $response->assertStatus(401)->assertExactJson(['error' => 'invalid_request']);
        }
        $this->assertSame(0, NotifyInboundEvent::withoutGlobalScopes()->count());
    }

    public function test_a_stale_or_future_timestamp_is_refused_even_with_a_correct_signature(): void
    {
        $this->signedPost($this->payload(), ['timestamp' => now()->subSeconds(301)->timestamp])->assertStatus(401);
        $this->signedPost($this->payload(['idempotency_key' => 'k2']), ['timestamp' => now()->addSeconds(301)->timestamp])->assertStatus(401);
        $this->assertSame(0, NotifyInboundEvent::withoutGlobalScopes()->count());
    }

    public function test_a_timestamp_inside_the_five_minute_window_is_accepted(): void
    {
        Queue::fake();

        $this->signedPost($this->payload(), ['timestamp' => now()->subSeconds(290)->timestamp])->assertStatus(202);
    }

    public function test_a_unknown_platform_and_a_platform_with_a_blank_secret_cannot_call(): void
    {
        $unknown = $this->signedPost($this->payload(['platform' => 'nonexistent']), ['secret' => '']);
        $blank = $this->signedPost($this->payload(['platform' => 'tasks']), ['secret' => '']);

        $unknown->assertStatus(401)->assertExactJson(['error' => 'invalid_request']);
        $blank->assertStatus(401)->assertExactJson(['error' => 'invalid_request']);
    }

    public function test_an_unknown_company_is_a_generic_404_and_records_nothing(): void
    {
        $this->signedPost($this->payload(['company_uuid' => '99999999-9999-4999-8999-999999999999']))
            ->assertStatus(404)->assertExactJson(['error' => 'not_found']);

        $this->assertSame(0, NotifyInboundEvent::withoutGlobalScopes()->count());
    }

    public function test_an_event_with_no_active_rule_is_recorded_as_no_rule_and_sends_nothing(): void
    {
        Queue::fake();
        $rulesBefore = NotifyRule::withoutGlobalScopes()->count();

        $response = $this->signedPost($this->payload(['event' => 'invoice.paid', 'idempotency_key' => 'paid-1']));

        $response->assertStatus(202)->assertJsonPath('status', 'no_rule');
        $this->assertSame('no_rule', NotifyInboundEvent::withoutGlobalScopes()->findOrFail($response->json('id'))->status);
        $this->assertSame(0, NotifyLog::withoutGlobalScopes()->count());
        $this->assertSame($rulesBefore, NotifyRule::withoutGlobalScopes()->count(), 'Ingest must never auto-create rules.');
        Queue::assertNothingPushed();
    }

    public function test_an_inactive_rule_counts_as_no_rule(): void
    {
        Queue::fake();
        NotifyRule::withoutGlobalScopes()->where('team_id', $this->acme->id)->update(['is_active' => false]);

        $this->signedPost($this->payload())->assertStatus(202)->assertJsonPath('status', 'no_rule');
        $this->assertSame(0, NotifyLog::withoutGlobalScopes()->count());
    }

    public function test_a_replay_returns_the_original_id_and_sends_nothing(): void
    {
        Queue::fake();

        $first = $this->signedPost($this->payload());
        $replay = $this->signedPost($this->payload(), ['timestamp' => now()->addSeconds(5)->timestamp]);

        $first->assertStatus(202);
        $replay->assertStatus(200)->assertJsonPath('id', $first->json('id'));
        $this->assertSame(1, NotifyLog::withoutGlobalScopes()->count());
        $this->assertSame(1, NotifyInboundEvent::withoutGlobalScopes()->count());
        Queue::assertPushed(DeliverNotifyLog::class, 1);
    }

    public function test_the_exact_same_signed_request_replayed_is_also_deduplicated(): void
    {
        Queue::fake();
        $raw = json_encode($this->payload());
        $timestamp = now()->timestamp;

        $a = $this->signedPost($raw, ['timestamp' => $timestamp]);
        $b = $this->signedPost($raw, ['timestamp' => $timestamp]);

        $a->assertStatus(202);
        $b->assertStatus(200)->assertJsonPath('id', $a->json('id'));
        $this->assertSame(1, NotifyLog::withoutGlobalScopes()->count());
    }

    public function test_idempotency_keys_are_scoped_per_platform_and_per_company(): void
    {
        Queue::fake();

        $this->signedPost($this->payload())->assertStatus(202);
        $this->signedPost($this->payload(['platform' => 'files']), ['secret' => self::OTHER_SECRET])->assertStatus(202);
        $this->signedPost($this->payload(['company_uuid' => self::GLOBEX]))->assertStatus(202);

        $this->assertSame(3, NotifyLog::withoutGlobalScopes()->count());
    }

    public function test_the_database_enforces_one_event_per_team_platform_and_key(): void
    {
        $row = ['team_id' => $this->acme->id, 'platform' => 'billing', 'idempotency_key' => 'dup', 'payload' => '{}', 'verified_at' => now(), 'status' => 'received', 'created_at' => now(), 'updated_at' => now()];
        DB::table('notify_inbound_events')->insert($row);

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('notify_inbound_events')->insert($row);
    }

    public function test_missing_or_oversized_idempotency_key_and_bad_recipient_are_rejected(): void
    {
        $this->signedPost(array_diff_key($this->payload(), ['idempotency_key' => 1]))->assertStatus(422);
        $this->signedPost($this->payload(['idempotency_key' => str_repeat('k', 121)]))->assertStatus(422);
        $this->signedPost($this->payload(['recipient' => ['email' => 'not-an-email']]))->assertStatus(422);
        $this->signedPost(array_diff_key($this->payload(), ['recipient' => 1]))->assertStatus(422);
        $this->signedPost($this->payload(['company_uuid' => 'not-a-uuid']))->assertStatus(422);
        $this->signedPost($this->payload(['event' => 'bad event!']))->assertStatus(422);

        $this->assertSame(0, NotifyInboundEvent::withoutGlobalScopes()->count());
    }

    public function test_an_oversized_body_is_refused(): void
    {
        $this->signedPost($this->payload(['data' => ['blob' => str_repeat('x', 70000)]]))->assertStatus(413);
    }

    public function test_requests_are_rate_limited_per_platform_and_company(): void
    {
        Queue::fake();
        config(['notify.ingest.rate_limit_per_minute' => 3]);

        foreach (range(1, 3) as $i) {
            $this->signedPost($this->payload(['idempotency_key' => "k{$i}"]))->assertStatus(202);
        }

        $this->signedPost($this->payload(['idempotency_key' => 'k4']))->assertStatus(429)->assertHeader('Retry-After');
        // Another company and another platform have their own budget.
        $this->signedPost($this->payload(['company_uuid' => self::GLOBEX, 'idempotency_key' => 'k5']))->assertStatus(202);
        $this->signedPost($this->payload(['platform' => 'files', 'idempotency_key' => 'k6']), ['secret' => self::OTHER_SECRET])->assertStatus(202);
        $this->assertSame(0, NotifyInboundEvent::withoutGlobalScopes()->where('idempotency_key', 'k4')->count());
    }

    public function test_a_platform_for_company_a_cannot_trigger_company_b_rule_or_channel(): void
    {
        Queue::fake();

        $this->signedPost($this->payload())->assertStatus(202);

        $log = NotifyLog::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($this->acme->id, $log->team_id);
        $this->assertSame($this->acmeChannel->id, $log->channel_id);
        $this->assertNotSame($this->globexChannel->id, $log->channel_id);
        $this->assertSame(0, NotifyLog::withoutGlobalScopes()->where('team_id', $this->globex->id)->count());
    }

    public function test_the_caller_cannot_pick_another_companys_channel(): void
    {
        Queue::fake();

        foreach ([$this->globexChannel->id, $this->globexChannel->name] as $i => $foreign) {
            // Globex's channel is named identically ("Primary Email"); the id is the unambiguous probe.
            if (is_string($foreign)) {
                NotifyChannel::withoutGlobalScopes()->whereKey($this->globexChannel->id)->update(['name' => 'Globex Only']);
                $foreign = 'Globex Only';
            }
            $this->signedPost($this->payload(['idempotency_key' => "probe{$i}", 'channel' => $foreign]))
                ->assertStatus(422)->assertJsonPath('error', 'unknown_channel');
        }

        $this->assertSame(0, NotifyLog::withoutGlobalScopes()->count());
    }

    public function test_the_caller_can_pick_one_of_its_own_companys_channels_by_name(): void
    {
        Queue::fake();
        $slack = NotifyChannel::create(['team_id' => $this->acme->id, 'type' => 'slack', 'name' => 'Ops Slack', 'config' => ['webhook_url' => 'https://hooks.slack.test/x']]);

        $this->signedPost($this->payload(['channel' => 'Ops Slack']))->assertStatus(202);

        $this->assertSame($slack->id, NotifyLog::withoutGlobalScopes()->firstOrFail()->channel_id);
    }

    public function test_a_rule_pointing_at_another_teams_channel_is_not_followed(): void
    {
        Queue::fake();
        // Corrupt row written around the model layer, as a stale pre-fix row could be.
        NotifyRule::withoutGlobalScopes()->where('team_id', $this->acme->id)->update(['channel_id' => $this->globexChannel->id]);

        $response = $this->signedPost($this->payload());

        $response->assertStatus(202)->assertJsonPath('status', 'failed');
        $this->assertSame(0, NotifyLog::withoutGlobalScopes()->count());
        Queue::assertNothingPushed();
    }

    public function test_the_rule_rate_ceiling_still_applies(): void
    {
        Queue::fake();
        NotifyRule::withoutGlobalScopes()->where('team_id', $this->acme->id)->update(['rate_ceiling_per_hour' => 1]);

        $this->signedPost($this->payload(['idempotency_key' => 'a']))->assertStatus(202)->assertJsonPath('status', 'routed');
        $this->signedPost($this->payload(['idempotency_key' => 'b']))->assertStatus(202)->assertJsonPath('status', 'ignored');

        $this->assertSame(1, NotifyLog::withoutGlobalScopes()->count());
    }

    public function test_fields_the_payload_marks_sensitive_are_redacted_at_rest_but_still_delivered(): void
    {
        Queue::fake();
        NotifyTemplate::withoutGlobalScopes()->where('team_id', $this->acme->id)->update(['body' => 'Reset link: {{ link }} for {{ name }}']);

        $response = $this->signedPost($this->payload([
            'data' => ['link' => 'https://app.test/reset?token=SUPERSECRET', 'name' => 'Amara', 'api_key' => 'sk_live_123'],
            'sensitive' => ['link'],
        ]));

        $response->assertStatus(202);
        $log = NotifyLog::withoutGlobalScopes()->firstOrFail();
        $this->assertStringNotContainsString('SUPERSECRET', $log->body_preview);
        $this->assertStringContainsString('[redacted]', $log->body_preview);
        $this->assertStringContainsString('Amara', $log->body_preview);
        // The real text is kept (encrypted) only until the send finishes.
        $this->assertStringNotContainsString('SUPERSECRET', (string) DB::table('notify_logs')->value('body'));
        $this->assertStringContainsString('SUPERSECRET', $log->body);

        $stored = json_encode(NotifyInboundEvent::withoutGlobalScopes()->firstOrFail()->payload);
        $this->assertStringNotContainsString('SUPERSECRET', $stored);
        $this->assertStringNotContainsString('sk_live_123', $stored, 'Keys that look like secrets are always redacted.');
        $this->assertStringContainsString('Amara', $stored);

        // Run the job for real: it sends the real text, then drops the plaintext copy.
        (new DeliverNotifyLog($log->id))->handle(app(NotifyDispatcher::class));
        $log->refresh();
        $this->assertStringContainsString('SUPERSECRET', $this->sentMessages()->first()->getOriginalMessage()->getTextBody());
        $this->assertSame('sent', $log->status);
        $this->assertNull($log->body);
        $this->assertStringNotContainsString('SUPERSECRET', $log->body_preview);
    }
}
