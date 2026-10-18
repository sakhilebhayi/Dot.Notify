<?php

namespace Tests\Feature\Notify;

use App\Models\NotifyChannel;
use App\Models\NotifyInboundEvent;
use App\Models\NotifyLog;
use App\Models\NotifyTemplate;
use App\Models\Team;
use App\Models\User;
use App\Services\Notify\EventEnabler;
use App\Services\Notify\NotifyEventCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SignsIngestRequests;
use Tests\TestCase;

/**
 * What the ingest API does with `data` once an event is in the catalogue:
 * unknown keys are dropped before storage and rendering, bad URLs are dropped,
 * nested arrays are refused, and unknown events are still accepted as no_rule.
 */
class IngestCatalogueTest extends TestCase
{
    use RefreshDatabase;
    use SignsIngestRequests;

    private const TASKS_SECRET = 'tasks-ingest-secret-for-tests';

    private const BILLING_SECRET = 'billing-ingest-secret-for-tests';

    private const COMPANY = '33333333-3333-4333-8333-333333333333';

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        config(['notify.ingest.secrets' => ['tasks' => self::TASKS_SECRET, 'billing' => self::BILLING_SECRET, 'hr' => 'hr-secret-for-tests'], 'notify.ingest.rate_limit_per_minute' => 1000]);

        $this->team = User::factory()->withPersonalTeam()->create()->currentTeam;
        DB::table('teams')->where('id', $this->team->id)->update(['infodot_team_uuid' => self::COMPANY]);
        $this->team = $this->team->fresh();
        NotifyChannel::create(['team_id' => $this->team->id, 'type' => 'email', 'name' => 'Primary Email']);
    }

    private function enable(string $platform, string $event): void
    {
        app(EventEnabler::class)->enable($this->team, $platform, $event);
    }

    /** @param  array<string, mixed>  $override */
    private function assignedPayload(array $override = []): array
    {
        return array_replace_recursive([
            'platform' => 'tasks',
            'company_uuid' => self::COMPANY,
            'event' => 'task.assigned',
            'recipient' => ['email' => 'amara@example.test'],
            'data' => [
                'task_title' => 'Ship the launch page',
                'board_name' => 'Launch',
                'assigned_by' => 'Bongani',
                'due_date' => '2026-11-02',
                'url' => 'https://tasks.infodot.app/boards/4?task=19',
            ],
            'idempotency_key' => 'task.assigned:19:abc123:2026-10-18',
        ], $override);
    }

    private function send(array $body, string $platform = 'tasks'): TestResponse
    {
        return $this->signedIngest($platform, $platform === 'billing' ? self::BILLING_SECRET : ($platform === 'hr' ? 'hr-secret-for-tests' : self::TASKS_SECRET), $body);
    }

    private function inbound(): NotifyInboundEvent
    {
        return NotifyInboundEvent::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    public function test_a_catalogued_event_renders_the_default_template_with_the_sanitised_data(): void
    {
        $this->enable('tasks', 'task.assigned');

        $this->send($this->assignedPayload())->assertStatus(202)->assertJsonPath('status', 'routed');

        $log = NotifyLog::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('New task: Ship the launch page', $log->subject);
        $this->assertStringContainsString('Assigned by: Bongani', $log->body_preview);
        $this->assertStringContainsString('Task: Ship the launch page', $log->body_preview);
        $this->assertStringContainsString('Due: 2026-11-02', $log->body_preview);
        $this->assertStringContainsString('https://tasks.infodot.app/boards/4?task=19', $log->body_preview);
        $this->assertStringNotContainsString('{{', $log->body_preview);
        $this->assertSame('task.assigned', $log->event);
    }

    public function test_the_email_that_goes_out_has_the_rendered_subject_and_body(): void
    {
        $this->enable('tasks', 'task.assigned');

        $this->send($this->assignedPayload())->assertStatus(202);

        $this->assertCount(1, $this->sentMessages());
        $message = $this->sentMessages()->first()->getOriginalMessage();
        $this->assertSame('New task: Ship the launch page', $message->getSubject());
        $this->assertStringContainsString('Open the task: https://tasks.infodot.app/boards/4?task=19', $message->getTextBody());
    }

    public function test_unknown_data_keys_are_dropped_before_storage_and_rendering(): void
    {
        Queue::fake();
        $this->enable('tasks', 'task.assigned');

        $this->send($this->assignedPayload(['data' => ['internal_note' => 'do not leak', 'password' => 'hunter2', 'meta' => ['a' => 'b']]]))
            ->assertStatus(202)->assertJsonPath('status', 'routed');

        $stored = $this->inbound()->payload;
        $this->assertSame(['task_title', 'board_name', 'assigned_by', 'due_date', 'url'], array_keys($stored['data']));
        $this->assertEqualsCanonicalizing(['internal_note', 'password', 'meta'], $stored['dropped_data_keys']);
        $this->assertStringNotContainsString('do not leak', json_encode($stored));
        $this->assertStringNotContainsString('hunter2', json_encode($stored));
        $this->assertStringNotContainsString('do not leak', NotifyLog::withoutGlobalScopes()->firstOrFail()->body_preview);
    }

    public function test_a_leave_reason_is_dropped_and_never_stored_or_sent(): void
    {
        $this->enable('hr', 'leave.requested');

        $this->send([
            'platform' => 'hr',
            'company_uuid' => self::COMPANY,
            'event' => 'leave.requested',
            'recipient' => ['email' => 'approver@example.test'],
            'data' => [
                'employee_name' => 'Amara Dlamini',
                'leave_type' => 'Sick',
                'start_date' => '2026-11-03',
                'end_date' => '2026-11-04',
                'days' => 2,
                'reason' => 'Oncology follow-up appointment',
                'url' => 'https://hr.infodot.app/leave/12',
            ],
            'idempotency_key' => 'leave.requested:12:def456:2026-10-18',
        ], 'hr')->assertStatus(202)->assertJsonPath('status', 'routed');

        $this->assertStringNotContainsString('Oncology', json_encode($this->inbound()->payload));
        $this->assertStringNotContainsString('Oncology', NotifyLog::withoutGlobalScopes()->firstOrFail()->body_preview);
        $this->assertStringNotContainsString('Oncology', $this->sentMessages()->first()->getOriginalMessage()->getTextBody());
        $this->assertMatchesRegularExpression('/^Days: 2$/m', $this->sentMessages()->first()->getOriginalMessage()->getTextBody());
    }

    /** @return array<string, array{string}> */
    public static function hostileUrls(): array
    {
        return [
            'javascript' => ['javascript:alert(document.cookie)'],
            'another host' => ['https://evil.example/phish'],
            'http' => ['http://tasks.infodot.app/boards/4'],
            'userinfo' => ['https://tasks.infodot.app@evil.example/'],
        ];
    }

    #[DataProvider('hostileUrls')]
    public function test_a_bad_url_is_dropped_everywhere_and_its_line_disappears_from_the_message(string $url): void
    {
        Queue::fake();
        $this->enable('tasks', 'task.assigned');

        $this->send($this->assignedPayload(['data' => ['url' => $url]]))->assertStatus(202)->assertJsonPath('status', 'routed');

        $stored = $this->inbound()->payload;
        $this->assertArrayNotHasKey('url', $stored['data']);
        $this->assertSame(['url'], $stored['dropped_data_keys']);

        $log = NotifyLog::withoutGlobalScopes()->firstOrFail();
        $this->assertStringNotContainsString($url, $log->body_preview);
        $this->assertStringNotContainsString('Open the task', $log->body_preview);
        $this->assertStringNotContainsString('{{', $log->body_preview);
        $this->assertStringContainsString('Task: Ship the launch page', $log->body_preview);
    }

    public function test_a_nested_array_in_an_allowed_key_is_a_422_and_nothing_is_stored(): void
    {
        Queue::fake();
        $this->enable('tasks', 'task.assigned');

        $response = $this->send($this->assignedPayload(['data' => ['task_title' => ['nested' => 'object']]]));

        $response->assertStatus(422)->assertJsonPath('error', 'invalid_request')->assertJsonStructure(['errors' => ['data.task_title']]);
        $this->assertSame(0, NotifyInboundEvent::withoutGlobalScopes()->count());
        $this->assertSame(0, NotifyLog::withoutGlobalScopes()->count());
    }

    public function test_long_values_are_capped_before_they_are_stored_or_rendered(): void
    {
        $this->enable('tasks', 'task.assigned');

        $this->send($this->assignedPayload(['data' => ['task_title' => str_repeat('x', 5000)]]))->assertStatus(202);

        $this->assertLessThanOrEqual(500, mb_strlen($this->inbound()->payload['data']['task_title']));
        $this->assertLessThan(1200, mb_strlen(NotifyLog::withoutGlobalScopes()->firstOrFail()->body_preview));
    }

    public function test_optional_fields_that_are_missing_leave_no_placeholder_and_no_empty_label(): void
    {
        $this->enable('tasks', 'task.assigned');

        $this->send($this->assignedPayload(['data' => ['assigned_by' => null, 'due_date' => null]]))->assertStatus(202);

        $log = NotifyLog::withoutGlobalScopes()->firstOrFail();
        $this->assertStringNotContainsString('{{', $log->body_preview);
        $this->assertStringNotContainsString('Assigned by', $log->body_preview);
        $this->assertStringNotContainsString('Due:', $log->body_preview);
        $this->assertStringContainsString('Task: Ship the launch page', $log->body_preview);
    }

    public function test_an_unknown_event_from_a_known_platform_is_accepted_as_no_rule(): void
    {
        Queue::fake();
        $this->enable('tasks', 'task.assigned');

        $response = $this->send($this->assignedPayload(['event' => 'task.archived', 'idempotency_key' => 'archived-1']));

        $response->assertStatus(202)->assertJsonPath('status', 'no_rule');
        $this->assertSame(0, NotifyLog::withoutGlobalScopes()->count());
        $this->assertNull(app(NotifyEventCatalogue::class)->find('task.archived'));
    }

    public function test_a_catalogued_event_from_another_platform_is_not_routed(): void
    {
        Queue::fake();
        $this->enable('tasks', 'task.assigned');

        // Billing holds its own secret, but task.assigned belongs to Dot.Tasks.
        $response = $this->send($this->assignedPayload(['platform' => 'billing', 'idempotency_key' => 'spoof-1']), 'billing');

        $response->assertStatus(202)->assertJsonPath('status', 'no_rule');
        $this->assertSame(0, NotifyLog::withoutGlobalScopes()->count());
        $this->assertStringContainsString('tasks', (string) $this->inbound()->note);
        $this->assertSame([], $this->inbound()->payload['data'], 'Nothing the wrong platform sent is kept.');
    }

    public function test_catalogued_events_take_their_notification_type_from_the_catalogue_not_the_caller(): void
    {
        Queue::fake();
        $this->enable('tasks', 'task.assigned');

        $this->send($this->assignedPayload(['notification_type' => 'security']))->assertStatus(202);

        $this->assertSame('transactional', NotifyLog::withoutGlobalScopes()->firstOrFail()->notification_type);
    }

    public function test_the_billing_invoice_event_accepts_what_dot_billing_sends(): void
    {
        $this->enable('billing', 'invoice.overdue');

        $this->send([
            'platform' => 'billing',
            'company_uuid' => self::COMPANY,
            'event' => 'invoice.overdue',
            'recipient' => ['email' => 'owner@example.test'],
            'data' => [
                'team_name' => 'Acme',
                'invoice_number' => 'INV-0007',
                'total' => 'R 1 150.00',
                'balance' => 'R 1 150.00',
                'currency' => 'ZAR',
                'due_date' => '2026-10-01',
                'days_overdue' => 17,
                'invoice_url' => 'https://billing.infodot.app/invoices/7',
            ],
            'idempotency_key' => 'invoice.overdue:7:2026-10-18',
        ], 'billing')->assertStatus(202)->assertJsonPath('status', 'routed');

        $log = NotifyLog::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('Invoice INV-0007 is overdue', $log->subject);
        $this->assertMatchesRegularExpression('/^Days overdue: 17$/m', $log->body_preview);
        $this->assertStringContainsString('https://billing.infodot.app/invoices/7', $log->body_preview);
        $this->assertSame([], $this->inbound()->payload['dropped_data_keys'] ?? []);
    }

    public function test_a_days_overdue_of_zero_is_shown_not_treated_as_missing(): void
    {
        $this->enable('billing', 'invoice.overdue');

        $this->send([
            'platform' => 'billing',
            'company_uuid' => self::COMPANY,
            'event' => 'invoice.overdue',
            'recipient' => ['email' => 'owner@example.test'],
            'data' => ['invoice_number' => 'INV-0008', 'days_overdue' => 0],
            'idempotency_key' => 'invoice.overdue:8:2026-10-18',
        ], 'billing')->assertStatus(202);

        $this->assertMatchesRegularExpression('/^Days overdue: 0$/m', NotifyLog::withoutGlobalScopes()->firstOrFail()->body_preview);
    }

    public function test_a_team_edited_template_keeps_its_wording_and_still_gets_only_catalogued_data(): void
    {
        $this->enable('tasks', 'task.assigned');
        NotifyTemplate::withoutGlobalScopes()->where('team_id', $this->team->id)->update(['subject' => 'Heads up: {{ task_title }}', 'body' => 'Hello! {{ task_title }} / {{ internal_note }}']);

        $this->send($this->assignedPayload(['data' => ['internal_note' => 'secret plan']]))->assertStatus(202);

        $log = NotifyLog::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('Heads up: Ship the launch page', $log->subject);
        $this->assertStringNotContainsString('secret plan', $log->body_preview);
    }

    public function test_events_with_no_rule_still_record_the_sanitised_data_only(): void
    {
        Queue::fake();

        $this->send($this->assignedPayload(['data' => ['internal_note' => 'x']]))->assertStatus(202)->assertJsonPath('status', 'no_rule');

        $this->assertArrayNotHasKey('internal_note', $this->inbound()->payload['data']);
    }

    public function test_an_event_switched_on_before_any_email_channel_exists_is_recorded_as_failed_and_sends_nothing(): void
    {
        Queue::fake();
        NotifyChannel::withoutGlobalScopes()->where('team_id', $this->team->id)->delete();
        $this->enable('tasks', 'task.assigned');

        $response = $this->send($this->assignedPayload());

        $response->assertStatus(202)->assertJsonPath('status', 'failed');
        $this->assertSame(0, NotifyLog::withoutGlobalScopes()->count());
        Queue::assertNothingPushed();
    }
}
