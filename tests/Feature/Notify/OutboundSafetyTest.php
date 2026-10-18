<?php

namespace Tests\Feature\Notify;

use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\Team;
use App\Models\User;
use App\Services\Notify\UnsafeUrl;
use App\Services\Notify\UrlGuard;
use App\Services\NotifyDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Webhook and Slack channels POST to team-supplied URLs, so a team admin
 * could aim them at the server's own network. No real DNS or HTTP here:
 * the resolver is injected and Http is faked.
 */
class OutboundSafetyTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = User::factory()->withPersonalTeam()->create()->currentTeam;
    }

    private function guard(array $dns = []): UrlGuard
    {
        return new UrlGuard(fn (string $host): array => $dns[$host] ?? ['93.184.216.34']);
    }

    /** @return array<string, array{0: string}> */
    public static function blockedUrls(): array
    {
        return [
            'loopback ip' => ['http://127.0.0.1/hook'],
            'loopback range' => ['http://127.9.9.9/hook'],
            'localhost name' => ['http://localhost/hook'],
            '10/8' => ['http://10.0.0.5/hook'],
            '172.16/12' => ['https://172.16.4.4/hook'],
            '192.168/16' => ['http://192.168.1.10/hook'],
            'cloud metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'link local' => ['http://169.254.10.10/'],
            'unspecified' => ['http://0.0.0.0/'],
            'cgnat' => ['http://100.64.0.1/'],
            'ipv6 loopback' => ['http://[::1]/hook'],
            'ipv6 unique local' => ['http://[fc00::1]/hook'],
            'ipv6 unique local fd' => ['http://[fd12:3456::1]/hook'],
            'ipv6 link local' => ['http://[fe80::1]/hook'],
            'ipv4 mapped loopback' => ['http://[::ffff:127.0.0.1]/hook'],
            'decimal ip' => ['http://2130706433/hook'],
            'hex ip' => ['http://0x7f000001/hook'],
            'short ip' => ['http://127.1/hook'],
            'file scheme' => ['file:///etc/passwd'],
            'ftp scheme' => ['ftp://example.com/x'],
            'gopher scheme' => ['gopher://example.com/x'],
            'no scheme' => ['example.com/hook'],
            'userinfo' => ['http://user:pass@example.com/hook'],
            'empty' => [''],
        ];
    }

    #[DataProvider('blockedUrls')]
    public function test_unsafe_urls_are_rejected_before_any_request(string $url): void
    {
        $this->expectException(UnsafeUrl::class);

        $this->guard(['localhost' => ['127.0.0.1']])->assertSafe($url);
    }

    public function test_a_public_https_and_http_url_pass_and_return_a_pinned_ip(): void
    {
        $this->assertSame('93.184.216.34', $this->guard()->assertSafe('https://hooks.example.test/a')['ip']);
        $this->assertSame(80, $this->guard()->assertSafe('http://hooks.example.test/a')['port']);
        $this->assertSame(8443, $this->guard()->assertSafe('https://hooks.example.test:8443/a')['port']);
    }

    public function test_a_public_looking_name_that_resolves_to_a_private_address_is_blocked(): void
    {
        $this->expectException(UnsafeUrl::class);

        $this->guard(['rebind.example.test' => ['10.1.2.3']])->assertSafe('https://rebind.example.test/hook');
    }

    public function test_every_resolved_address_must_be_public_not_just_the_first(): void
    {
        $this->expectException(UnsafeUrl::class);

        $this->guard(['mixed.example.test' => ['93.184.216.34', '169.254.169.254']])->assertSafe('https://mixed.example.test/hook');
    }

    public function test_a_name_that_does_not_resolve_is_blocked(): void
    {
        $this->expectException(UnsafeUrl::class);

        $this->guard(['nx.example.test' => []])->assertSafe('https://nx.example.test/hook');
    }

    public function test_a_webhook_channel_aimed_at_the_metadata_service_fails_permanently_without_a_request(): void
    {
        Http::fake();
        $log = $this->logFor('webhook', ['url' => 'http://169.254.169.254/latest/meta-data/iam']);

        app(NotifyDispatcher::class)->dispatch($log);

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('blocked', strtolower($log->failure_reason));
        $this->assertSame(1, $log->attempts);
        $this->assertNull($log->next_retry_at);
        Http::assertNothingSent();
    }

    public function test_a_slack_channel_aimed_at_a_private_address_is_blocked(): void
    {
        Http::fake();
        $log = $this->logFor('slack', ['webhook_url' => 'https://10.0.0.7/services/T0/B0/X']);

        app(NotifyDispatcher::class)->dispatch($log);

        $this->assertSame('failed', $log->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_a_hostname_that_resolves_private_at_send_time_is_blocked(): void
    {
        Http::fake();
        $this->app->instance(UrlGuard::class, $this->guard(['hooks.example.test' => ['192.168.0.9']]));
        $log = $this->logFor('webhook', ['url' => 'https://hooks.example.test/in']);

        app(NotifyDispatcher::class)->dispatch($log);

        $this->assertSame('failed', $log->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_requests_use_a_five_second_timeout_and_never_follow_redirects(): void
    {
        $seen = [];
        Http::fake(function (Request $request, array $options) use (&$seen) {
            $seen = $options;

            return Http::response('ok', 200);
        });
        $log = $this->logFor('webhook', ['url' => 'https://hooks.example.test/in']);

        app(NotifyDispatcher::class)->dispatch($log);

        $this->assertSame('sent', $log->fresh()->status);
        $this->assertEquals(5, $seen['timeout']);
        $this->assertEquals(5, $seen['connect_timeout']);
        $this->assertFalse($seen['allow_redirects']);
    }

    public function test_a_redirect_is_not_followed_and_fails_permanently(): void
    {
        Http::fake(['hooks.example.test/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/'])]);
        $log = $this->logFor('webhook', ['url' => 'https://hooks.example.test/in']);

        app(NotifyDispatcher::class)->dispatch($log);

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('redirect', strtolower($log->failure_reason));
        Http::assertSentCount(1);
    }

    public function test_outgoing_webhooks_are_signed_when_the_channel_has_a_secret(): void
    {
        $captured = null;
        Http::fake(function (Request $request) use (&$captured) {
            $captured = $request;

            return Http::response('ok', 200);
        });
        $log = $this->logFor('webhook', ['url' => 'https://hooks.example.test/in', 'secret' => 'channel-secret']);

        app(NotifyDispatcher::class)->dispatch($log);

        $timestamp = $captured->header('X-Dot-Timestamp')[0];
        $this->assertEqualsWithDelta(now()->timestamp, (int) $timestamp, 5);
        $this->assertSame(
            'sha256='.hash_hmac('sha256', $timestamp.'.'.$captured->body(), 'channel-secret'),
            $captured->header('X-Dot-Signature')[0],
        );
        $this->assertSame('amara@example.test', $captured->data()['recipient']);
    }

    public function test_outgoing_webhooks_are_unsigned_without_a_secret(): void
    {
        $captured = null;
        Http::fake(function (Request $request) use (&$captured) {
            $captured = $request;

            return Http::response('ok', 200);
        });

        app(NotifyDispatcher::class)->dispatch($this->logFor('webhook', ['url' => 'https://hooks.example.test/in']));

        $this->assertFalse($captured->hasHeader('X-Dot-Signature'));
    }

    public function test_secret_urls_in_error_messages_are_scrubbed_before_they_are_stored(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect to https://hooks.slack.test/services/T000/B000/SUPERSECRETTOKEN port 443'));
        $log = $this->logFor('slack', ['webhook_url' => 'https://hooks.slack.test/services/T000/B000/SUPERSECRETTOKEN']);

        app(NotifyDispatcher::class)->dispatch($log);

        $log->refresh();
        $this->assertSame('retrying', $log->status);
        $this->assertStringNotContainsString('SUPERSECRETTOKEN', $log->last_error);
        $this->assertStringContainsString('hooks.slack.test', $log->last_error);
    }

    private function logFor(string $type, array $config): NotifyLog
    {
        $channel = NotifyChannel::create(['team_id' => $this->team->id, 'type' => $type, 'name' => ucfirst($type), 'config' => $config]);

        return NotifyLog::create(['team_id' => $this->team->id, 'channel_id' => $channel->id, 'recipient' => 'amara@example.test', 'subject' => 'Subject', 'body_preview' => 'Body', 'status' => 'queued']);
    }
}
