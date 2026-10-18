<?php

namespace Tests\Feature\Notify;

use App\Jobs\DeliverNotifyLog;
use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\Team;
use App\Models\User;
use App\Services\NotifyDispatcher;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Delivery is a queued job with a row claim, retries with backoff, and a
 * thin sweeper. Every outbound call here is faked (Http::fake, array mailer).
 */
class QueuedDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = User::factory()->withPersonalTeam()->create()->currentTeam;
    }

    private function webhookLog(array $logOverrides = [], array $config = ['url' => 'https://hooks.example.test/in']): NotifyLog
    {
        $channel = NotifyChannel::create(['team_id' => $this->team->id, 'type' => 'webhook', 'name' => 'Hook', 'config' => $config]);

        return NotifyLog::create(array_merge([
            'team_id' => $this->team->id,
            'channel_id' => $channel->id,
            'recipient' => 'amara@example.test',
            'subject' => 'Subject',
            'body_preview' => 'Body',
            'status' => 'queued',
        ], $logOverrides));
    }

    private function emailLog(string $recipient = 'amara@example.test'): NotifyLog
    {
        $channel = NotifyChannel::create(['team_id' => $this->team->id, 'type' => 'email', 'name' => 'Email']);

        return NotifyLog::create(['team_id' => $this->team->id, 'channel_id' => $channel->id, 'recipient' => $recipient, 'subject' => 'S', 'body_preview' => 'B', 'status' => 'queued']);
    }

    private function runJob(NotifyLog $log): void
    {
        (new DeliverNotifyLog($log->id))->handle(app(NotifyDispatcher::class));
    }

    public function test_the_job_is_queued_unique_per_log_and_retries_five_times_with_exponential_backoff(): void
    {
        $job = new DeliverNotifyLog(42);

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertInstanceOf(ShouldBeUniqueUntilProcessing::class, $job);
        $this->assertSame(5, $job->tries);
        $this->assertSame('42', (string) $job->uniqueId());
        $backoff = $job->backoff();
        $this->assertSame($backoff, collect($backoff)->sort()->values()->all(), 'backoff must not shrink');
        $this->assertGreaterThan($backoff[0], $backoff[1]);
        $this->assertGreaterThan($backoff[1], $backoff[2]);
    }

    public function test_a_transient_failure_schedules_a_retry_then_the_next_attempt_succeeds(): void
    {
        Queue::fake();
        Http::fake(['hooks.example.test/*' => Http::sequence()->push('boom', 500)->push(['ok' => true], 200)]);
        $log = $this->webhookLog();

        $this->runJob($log);

        $log->refresh();
        $this->assertSame('retrying', $log->status);
        $this->assertSame(1, $log->attempts);
        $this->assertSame('Webhook returned HTTP 500.', $log->last_error);
        $this->assertNotNull($log->next_retry_at);
        $this->assertEqualsWithDelta(now()->addSeconds(30)->timestamp, $log->next_retry_at->timestamp, 2);
        $this->assertNull($log->sent_at);
        Queue::assertPushed(DeliverNotifyLog::class, fn (DeliverNotifyLog $job) => $job->logId === $log->id && $job->delay === 30);

        // Running again before the retry is due must not send anything.
        $this->runJob($log);
        Http::assertSentCount(1);

        $this->travel(31)->seconds();
        $this->runJob($log);

        $log->refresh();
        $this->assertSame('sent', $log->status);
        $this->assertSame(2, $log->attempts);
        $this->assertNotNull($log->sent_at);
        $this->assertNull($log->next_retry_at);
        Http::assertSentCount(2);
    }

    public function test_a_connection_error_and_a_429_are_treated_as_transient(): void
    {
        Queue::fake();
        Http::fake(['hooks.example.test/a' => fn () => throw new ConnectionException('cURL error 28: timed out'), 'hooks.example.test/b' => Http::response('slow down', 429)]);

        $a = $this->webhookLog(config: ['url' => 'https://hooks.example.test/a']);
        $b = $this->webhookLog(config: ['url' => 'https://hooks.example.test/b']);
        $this->runJob($a);
        $this->runJob($b);

        $this->assertSame('retrying', $a->fresh()->status);
        $this->assertSame('retrying', $b->fresh()->status);
    }

    public function test_after_five_attempts_a_still_failing_log_is_failed_for_good(): void
    {
        Queue::fake();
        Http::fake(['hooks.example.test/*' => Http::response('boom', 503)]);
        $log = $this->webhookLog();

        foreach (range(1, 5) as $attempt) {
            $this->travel(1)->hours();
            $this->runJob($log);
        }

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame(5, $log->attempts);
        $this->assertSame('Webhook returned HTTP 503.', $log->failure_reason);
        $this->assertNull($log->next_retry_at);
        Queue::assertPushed(DeliverNotifyLog::class, 4); // retries scheduled after attempts 1-4 only
        Http::assertSentCount(5);

        $this->travel(1)->hours();
        $this->runJob($log);
        Http::assertSentCount(5);
    }

    public function test_a_permanent_4xx_fails_immediately_without_retry(): void
    {
        Queue::fake();
        Http::fake(['hooks.example.test/*' => Http::response('gone', 410)]);
        $log = $this->webhookLog();

        $this->runJob($log);

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame(1, $log->attempts);
        $this->assertSame('Webhook returned HTTP 410.', $log->failure_reason);
        $this->assertNull($log->next_retry_at);
        Queue::assertNothingPushed();
        Http::assertSentCount(1);
    }

    public function test_slack_4xx_is_permanent_and_5xx_is_transient(): void
    {
        Queue::fake();
        Http::fake(['hooks.slack.test/a' => Http::response('no_service', 404), 'hooks.slack.test/b' => Http::response('oops', 502)]);
        $mk = fn (string $path) => NotifyLog::create(['team_id' => $this->team->id, 'channel_id' => NotifyChannel::create(['team_id' => $this->team->id, 'type' => 'slack', 'name' => 'S'.$path, 'config' => ['webhook_url' => 'https://hooks.slack.test/'.$path]])->id, 'recipient' => '#ops', 'body_preview' => 'x', 'status' => 'queued']);
        $a = $mk('a');
        $b = $mk('b');

        $this->runJob($a);
        $this->runJob($b);

        $this->assertSame('failed', $a->fresh()->status);
        $this->assertSame('retrying', $b->fresh()->status);
    }

    public function test_an_invalid_email_address_fails_permanently_and_sends_nothing(): void
    {
        Queue::fake();
        $log = $this->emailLog('not-an-address');

        $this->runJob($log);

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame('Invalid recipient email address.', $log->failure_reason);
        $this->assertCount(0, $this->sentMessages());
        Queue::assertNothingPushed();
    }

    public function test_a_mail_transport_error_is_transient(): void
    {
        Queue::fake();
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('smtp connection refused'));
        $log = $this->emailLog();

        $this->runJob($log);

        $this->assertSame('retrying', $log->fresh()->status);
        $this->assertSame('smtp connection refused', $log->fresh()->last_error);
    }

    public function test_a_sent_email_goes_out_once_and_is_marked_sent(): void
    {
        $log = $this->emailLog();

        $this->runJob($log);
        $this->runJob($log);

        $this->assertSame('sent', $log->fresh()->status);
        $this->assertCount(1, $this->sentMessages());
    }

    public function test_two_workers_cannot_both_send_the_same_log(): void
    {
        Queue::fake();
        $log = $this->webhookLog();
        $secondWorker = 0;

        // While worker A is mid-send (inside the HTTP call), worker B picks up the same log.
        Http::fake(function (Request $request) use ($log, &$secondWorker) {
            $secondWorker++;
            if ($secondWorker === 1) {
                $this->runJob($log);
            }

            return Http::response(['ok' => true], 200);
        });

        $this->runJob($log);

        Http::assertSentCount(1);
        $this->assertSame('sent', $log->fresh()->status);
        $this->assertSame(1, $log->fresh()->attempts);
    }

    public function test_a_stale_in_memory_copy_cannot_claim_a_row_someone_else_already_took(): void
    {
        Http::fake(['hooks.example.test/*' => Http::response('ok', 200)]);
        $log = $this->webhookLog();
        $staleCopy = NotifyLog::findOrFail($log->id); // still says "queued"

        app(NotifyDispatcher::class)->dispatch($log);
        app(NotifyDispatcher::class)->dispatch($staleCopy);

        Http::assertSentCount(1);
        $this->assertSame(1, $log->fresh()->attempts);
    }

    public function test_a_row_stuck_in_sending_after_a_worker_crash_is_failed_not_resent(): void
    {
        Http::fake();
        $log = $this->webhookLog(['status' => 'sending', 'attempts' => 1, 'last_attempt_at' => now()->subMinutes(30)]);
        $fresh = $this->webhookLog(['status' => 'sending', 'attempts' => 1, 'last_attempt_at' => now()->subMinute()]);

        $this->artisan('notify:dispatch-queued')->assertExitCode(0);

        $this->assertSame('failed', $log->fresh()->status);
        $this->assertStringContainsString('outcome unknown', $log->fresh()->failure_reason);
        $this->assertSame('sending', $fresh->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_the_sweeper_only_enqueues_due_logs_in_bounded_batches_and_never_sends_inline(): void
    {
        Queue::fake();
        Http::fake();
        $channel = NotifyChannel::create(['team_id' => $this->team->id, 'type' => 'webhook', 'name' => 'Hook', 'config' => ['url' => 'https://hooks.example.test/in']]);
        $base = ['team_id' => $this->team->id, 'channel_id' => $channel->id, 'recipient' => 'a@example.test', 'created_at' => now(), 'updated_at' => now()];
        $rows = [];
        foreach (range(1, 130) as $i) {
            $rows[] = $base + ['status' => 'queued'];
        }
        \DB::table('notify_logs')->insert($rows);
        $dueRetry = NotifyLog::create(['team_id' => $this->team->id, 'channel_id' => $channel->id, 'recipient' => 'r@example.test', 'status' => 'retrying', 'attempts' => 1, 'next_retry_at' => now()->subMinute()]);
        $laterRetry = NotifyLog::create(['team_id' => $this->team->id, 'channel_id' => $channel->id, 'recipient' => 'l@example.test', 'status' => 'retrying', 'attempts' => 1, 'next_retry_at' => now()->addHour()]);
        $sent = NotifyLog::create(['team_id' => $this->team->id, 'channel_id' => $channel->id, 'recipient' => 's@example.test', 'status' => 'sent']);

        $this->artisan('notify:dispatch-queued')->assertExitCode(0);

        Queue::assertPushed(DeliverNotifyLog::class, 100);
        Queue::assertNotPushed(DeliverNotifyLog::class, fn (DeliverNotifyLog $j) => in_array($j->logId, [$laterRetry->id, $sent->id], true));
        Http::assertNothingSent();
        $this->assertSame(130, NotifyLog::where('status', 'queued')->count(), 'The sweeper itself must not change delivery state.');

        // A second sweep does not pile duplicates on: jobs already waiting are unique per log id.
        // With a bigger batch it reaches the remaining queued rows and the one due retry, but still
        // not the retry that is not due yet or the row that is already sent.
        $this->artisan('notify:dispatch-queued', ['--limit' => 500])->assertExitCode(0);
        Queue::assertPushed(DeliverNotifyLog::class, 131);
        Queue::assertPushed(DeliverNotifyLog::class, fn (DeliverNotifyLog $j) => $j->logId === $dueRetry->id);
        Queue::assertNotPushed(DeliverNotifyLog::class, fn (DeliverNotifyLog $j) => in_array($j->logId, [$laterRetry->id, $sent->id], true));
    }

    public function test_the_existing_end_to_end_path_still_delivers_under_the_sync_driver(): void
    {
        $log = $this->emailLog();

        $this->artisan('notify:dispatch-queued')->assertExitCode(0);

        $this->assertSame('sent', $log->fresh()->status);
    }
}
