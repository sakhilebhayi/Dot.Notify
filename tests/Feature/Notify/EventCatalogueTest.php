<?php

namespace Tests\Feature\Notify;

use App\Models\NotifyTemplate;
use App\Services\Notify\InvalidEventData;
use App\Services\Notify\NotifyEventCatalogue;
use App\Services\Notify\Redactor;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The event catalogue is the contract the calling Dot apps are written against:
 * these names and data keys must not change without a version bump.
 */
class EventCatalogueTest extends TestCase
{
    /** platform => event => allowed data keys, exactly as the calling apps send them. */
    private const CONTRACT = [
        'tasks' => [
            'task.assigned' => ['task_title', 'board_name', 'assigned_by', 'due_date', 'url'],
            'task.due_soon' => ['task_title', 'board_name', 'due_date', 'url'],
            'task.escalated' => ['task_title', 'board_name', 'owner_name', 'url'],
            'task.mentioned' => ['task_title', 'board_name', 'mentioned_by', 'url'],
        ],
        'hr' => [
            'leave.requested' => ['employee_name', 'leave_type', 'start_date', 'end_date', 'days', 'url'],
            'leave.decided' => ['leave_type', 'start_date', 'end_date', 'decision', 'decided_by', 'url'],
        ],
        'auction' => [
            'auction.outbid' => ['auction_title', 'your_bid', 'current_price', 'currency', 'ends_at', 'url'],
            'auction.won' => ['auction_title', 'final_price', 'currency', 'url'],
            'auction.sold' => ['auction_title', 'final_price', 'currency', 'status', 'url'],
            'auction.ending_soon' => ['auction_title', 'current_price', 'currency', 'ends_at', 'url'],
        ],
        'ehail' => [
            'ride.accepted' => ['driver_name', 'vehicle', 'url'],
            'ride.cancelled' => ['cancelled_by_role', 'reason', 'url'],
            'ride.expired' => ['url'],
            'driver.application_decided' => ['decision', 'fleet_name', 'note', 'url'],
        ],
        'farms' => [
            'harvest.recorded' => ['farm_name', 'field_name', 'crop', 'quantity', 'unit', 'harvested_at', 'url'],
        ],
        // Exactly what Dot.Billing's NotifyClient::invoiceOverduePayload() sends (Dot.Billing PR #6).
        'billing' => [
            'invoice.overdue' => ['team_name', 'invoice_number', 'total', 'balance', 'currency', 'due_date', 'days_overdue', 'invoice_url'],
        ],
    ];

    private NotifyEventCatalogue $catalogue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue = app(NotifyEventCatalogue::class);
    }

    /** @return array<string, array{string, string, array<int, string>}> */
    public static function contract(): array
    {
        $rows = [];
        foreach (self::CONTRACT as $platform => $events) {
            foreach ($events as $event => $keys) {
                $rows[$event] = [$platform, $event, $keys];
            }
        }

        return $rows;
    }

    /** @return array<string, array{string, string}> */
    public static function events(): array
    {
        return array_map(fn (array $row) => [$row[0], $row[1]], self::contract());
    }

    /** @param  array<int, string>  $keys */
    #[DataProvider('contract')]
    public function test_every_event_has_exactly_the_agreed_name_and_data_keys(string $platform, string $event, array $keys): void
    {
        $found = $this->catalogue->find($event, $platform);

        $this->assertNotNull($found, "{$platform} {$event} is missing from the catalogue.");
        $this->assertSame($platform, $found['platform']);
        $this->assertSame($event, $found['event']);
        $this->assertEqualsCanonicalizing($keys, $this->catalogue->allowedKeys($event));
    }

    public function test_the_catalogue_holds_exactly_the_agreed_events_and_is_versioned(): void
    {
        $expected = [];
        foreach (self::CONTRACT as $events) {
            $expected = array_merge($expected, array_keys($events));
        }

        $this->assertEqualsCanonicalizing($expected, array_keys($this->catalogue->all()));
        $this->assertCount(16, $this->catalogue->all());
        $this->assertSame(1, $this->catalogue->version());
        $this->assertSame(array_keys(self::CONTRACT), array_keys($this->catalogue->platforms()));
    }

    #[DataProvider('events')]
    public function test_every_event_is_fully_described(string $platform, string $event): void
    {
        $definition = $this->catalogue->find($event);

        $this->assertNotSame('', trim($definition['label']));
        $this->assertNotSame('', trim($definition['description']));
        $this->assertSame('email', $definition['channel']);
        $this->assertContains($definition['recipient'], ['user', 'approvers']);
        $this->assertNotSame('', trim($definition['template']['subject']));
        $this->assertNotSame('', trim($definition['template']['body']));
        $this->assertContains($definition['notification_type'], ['transactional', 'security', 'marketing', 'product_updates']);
        $this->assertNotContains($definition['notification_type'], ['security'], 'No catalogued event is a security notice today.');
    }

    #[DataProvider('events')]
    public function test_template_variables_are_a_subset_of_the_allowed_keys(string $platform, string $event): void
    {
        $definition = $this->catalogue->find($event);
        $allowed = $this->catalogue->allowedKeys($event);

        foreach (['subject', 'body'] as $part) {
            $unknown = array_diff($this->catalogue->templateVariables($definition['template'][$part]), $allowed);
            $this->assertSame([], array_values($unknown), "{$event} {$part} uses variables the caller cannot send.");
        }
    }

    #[DataProvider('events')]
    public function test_default_templates_render_with_sample_data_and_leave_no_placeholders(string $platform, string $event): void
    {
        $sample = $this->sampleData($platform, $event);
        $default = $this->catalogue->defaultTemplate($event);

        $template = new NotifyTemplate(['subject' => $default['subject'], 'body' => $default['body']]);
        $vars = Redactor::flatten($sample);

        foreach ([$template->render($vars), $template->renderSubject($vars)] as $text) {
            $this->assertStringNotContainsString('{{', $text);
            $this->assertStringNotContainsString('}}', $text);
        }

        // Every sample value that the template uses really appears in the rendered text.
        $used = array_unique(array_merge(
            $this->catalogue->templateVariables($default['body']),
            $this->catalogue->templateVariables($default['subject']),
        ));
        $rendered = $template->renderSubject($vars)."\n".$template->render($vars);
        foreach ($used as $key) {
            $this->assertStringContainsString((string) $sample[$key], $rendered, "{$event}: {$key} is not shown.");
        }
    }

    #[DataProvider('events')]
    public function test_the_secret_redactor_never_masks_a_catalogued_key(string $platform, string $event): void
    {
        $sample = $this->sampleData($platform, $event);

        $this->assertSame($sample, Redactor::payload($sample));
    }

    public function test_leave_requests_can_never_carry_a_reason(): void
    {
        $this->assertNotContains('reason', $this->catalogue->allowedKeys('leave.requested'));
        $this->assertNotContains('reason', $this->catalogue->allowedKeys('leave.decided'));
        $this->assertSame('approvers', $this->catalogue->find('leave.requested')['recipient']);

        $clean = $this->catalogue->validate('hr', 'leave.requested', [
            'employee_name' => 'Amara',
            'reason' => 'Medical appointment',
            'leave_type' => 'sick',
        ]);

        $this->assertSame(['employee_name' => 'Amara', 'leave_type' => 'sick'], $clean);
    }

    public function test_the_recipient_kind_is_approvers_only_for_leave_requests(): void
    {
        $approvers = array_keys(array_filter($this->catalogue->all(), fn (array $e) => $e['recipient'] === 'approvers'));

        $this->assertSame(['leave.requested'], $approvers);
    }

    public function test_an_event_is_found_only_on_the_platform_that_owns_it(): void
    {
        $this->assertNotNull($this->catalogue->find('task.assigned', 'tasks'));
        $this->assertNull($this->catalogue->find('task.assigned', 'billing'));
        $this->assertNull($this->catalogue->find('task.unheard_of', 'tasks'));
        $this->assertSame('tasks', $this->catalogue->owner('task.assigned'));
        $this->assertNull($this->catalogue->owner('task.unheard_of'));
    }

    public function test_every_catalogued_platform_is_a_registered_platform_with_an_ingest_secret_slot(): void
    {
        foreach (array_keys($this->catalogue->platforms()) as $platform) {
            $this->assertArrayHasKey($platform, config('ecosystem.platforms'));
            $this->assertArrayHasKey($platform, config('notify.ingest.secrets'));
        }
    }

    public function test_unknown_keys_are_dropped(): void
    {
        $result = $this->catalogue->inspect('tasks', 'task.assigned', [
            'task_title' => 'Ship it',
            'board_name' => 'Launch',
            'password' => 'hunter2',
            'extra' => 'x',
            'taskTitle' => 'wrong case',
        ]);

        $this->assertSame(['task_title' => 'Ship it', 'board_name' => 'Launch'], $result['data']);
        $this->assertEqualsCanonicalizing(['password', 'extra', 'taskTitle'], $result['dropped']);
    }

    public function test_null_values_are_dropped_and_scalars_keep_their_type(): void
    {
        $clean = $this->catalogue->validate('hr', 'leave.requested', [
            'employee_name' => 'Amara',
            'leave_type' => null,
            'days' => 3,
        ]);

        $this->assertSame(['employee_name' => 'Amara', 'days' => 3], $clean);
    }

    public function test_long_strings_are_capped_and_control_characters_flattened(): void
    {
        config(['notify_events.max_value_length' => 40]);

        $clean = $this->catalogue->validate('tasks', 'task.assigned', [
            'task_title' => str_repeat('a', 400),
            'board_name' => "Line one\r\nBcc: attacker@example.test\x00\x07",
        ]);

        $this->assertSame(40, mb_strlen($clean['task_title']));
        $this->assertStringEndsWith('…', $clean['task_title']);
        $this->assertSame('Line one Bcc: attacker@example.test', $clean['board_name']);
    }

    public function test_the_default_length_cap_is_a_few_hundred_characters(): void
    {
        $clean = $this->catalogue->validate('tasks', 'task.assigned', ['task_title' => str_repeat('é', 5000)]);

        $this->assertLessThanOrEqual(500, mb_strlen($clean['task_title']));
        $this->assertGreaterThan(100, mb_strlen($clean['task_title']));
    }

    public function test_a_nested_array_in_an_allowed_key_is_rejected(): void
    {
        try {
            $this->catalogue->validate('tasks', 'task.assigned', ['task_title' => ['nested' => 'x'], 'board_name' => 'Launch']);
            $this->fail('A nested array was accepted.');
        } catch (InvalidEventData $e) {
            $this->assertArrayHasKey('data.task_title', $e->errors());
        }
    }

    public function test_a_nested_array_in_an_unknown_key_is_simply_dropped(): void
    {
        $result = $this->catalogue->inspect('tasks', 'task.assigned', ['task_title' => 'Ship it', 'meta' => ['a' => ['b' => 'c']]]);

        $this->assertSame(['task_title' => 'Ship it'], $result['data']);
        $this->assertSame(['meta'], $result['dropped']);
    }

    /** @return array<string, array{string}> */
    public static function badUrls(): array
    {
        return [
            'javascript scheme' => ['javascript:alert(1)'],
            'mixed case javascript' => ['JaVaScRiPt:alert(1)'],
            'data uri' => ['data:text/html,<script>alert(1)</script>'],
            'plain http' => ['http://tasks.infodot.app/boards/1'],
            'another host' => ['https://evil.example/boards/1'],
            'lookalike suffix' => ['https://tasks.infodot.app.evil.example/boards/1'],
            'lookalike prefix' => ['https://eviltasks.infodot.app/boards/1'],
            'another platform host' => ['https://billing.infodot.app/invoices/1'],
            'userinfo trick' => ['https://tasks.infodot.app@evil.example/boards/1'],
            'userinfo with password' => ['https://user:pass@tasks.infodot.app/boards/1'],
            'backslash trick' => ['https://evil.example\\@tasks.infodot.app/'],
            'odd port' => ['https://tasks.infodot.app:8443/boards/1'],
            'relative path' => ['/boards/1'],
            'protocol relative' => ['//tasks.infodot.app/boards/1'],
            'no scheme' => ['tasks.infodot.app/boards/1'],
            'whitespace' => ['https://tasks.infodot.app/boards/1 onclick=x'],
            'newline' => ["https://tasks.infodot.app/boards/1\r\nBcc: a@example.test"],
            'too long' => ['https://tasks.infodot.app/'.'a'.str_repeat('b', 2100)],
        ];
    }

    #[DataProvider('badUrls')]
    public function test_a_url_that_is_not_https_on_the_platforms_own_host_is_dropped(string $url): void
    {
        $result = $this->catalogue->inspect('tasks', 'task.assigned', ['task_title' => 'Ship it', 'url' => $url]);

        $this->assertSame(['task_title' => 'Ship it'], $result['data']);
        $this->assertSame(['url'], $result['dropped']);
        $this->assertFalse($this->catalogue->isAllowedUrl('tasks', $url));
    }

    public function test_a_url_that_is_not_a_string_is_dropped(): void
    {
        $result = $this->catalogue->inspect('tasks', 'task.assigned', ['url' => 12345]);

        $this->assertSame([], $result['data']);
    }

    /** @return array<string, array{string}> */
    public static function goodUrls(): array
    {
        return [
            'plain' => ['https://tasks.infodot.app/boards/1'],
            'query and fragment' => ['https://tasks.infodot.app/boards/1?task=9&x=%20y#comments'],
            'host case' => ['https://TASKS.infodot.app/boards/1'],
            'explicit 443' => ['https://tasks.infodot.app:443/boards/1'],
            'bare host' => ['https://tasks.infodot.app'],
        ];
    }

    #[DataProvider('goodUrls')]
    public function test_an_https_url_on_the_platforms_registry_host_is_kept(string $url): void
    {
        $this->assertSame(['url' => $url], $this->catalogue->validate('tasks', 'task.assigned', ['url' => $url]));
    }

    public function test_the_allowed_host_follows_the_ecosystem_registry_per_environment(): void
    {
        config(['ecosystem.platforms.tasks.url' => 'https://tasks.infodot.co.za']);

        $this->assertTrue($this->catalogue->isAllowedUrl('tasks', 'https://tasks.infodot.co.za/boards/1'));
        $this->assertFalse($this->catalogue->isAllowedUrl('tasks', 'https://tasks.infodot.app/boards/1'));
    }

    public function test_billing_urls_are_checked_on_the_billing_host_under_its_own_key(): void
    {
        $ok = $this->catalogue->validate('billing', 'invoice.overdue', ['invoice_url' => 'https://billing.infodot.app/invoices/7']);
        $bad = $this->catalogue->inspect('billing', 'invoice.overdue', ['invoice_url' => 'https://tasks.infodot.app/invoices/7']);

        $this->assertSame(['invoice_url' => 'https://billing.infodot.app/invoices/7'], $ok);
        $this->assertSame([], $bad['data']);
    }

    public function test_a_platform_with_no_usable_registry_host_accepts_no_urls_at_all(): void
    {
        config(['ecosystem.platforms.tasks.url' => '']);

        $this->assertFalse($this->catalogue->isAllowedUrl('tasks', 'https://tasks.infodot.app/boards/1'));
        $this->assertFalse($this->catalogue->isAllowedUrl('not-a-platform', 'https://tasks.infodot.app/boards/1'));
    }

    public function test_an_event_the_catalogue_does_not_know_cannot_be_validated(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->catalogue->validate('tasks', 'task.unheard_of', ['a' => 'b']);
    }

    /** @return array<string, mixed> */
    private function sampleData(string $platform, string $event): array
    {
        $host = parse_url((string) config("ecosystem.platforms.{$platform}.url"), PHP_URL_HOST);
        $sample = [];

        foreach ($this->catalogue->allowedKeys($event) as $key) {
            $sample[$key] = ($key === 'url' || str_ends_with($key, '_url'))
                ? "https://{$host}/sample/1"
                : 'Sample '.str_replace('_', ' ', $key);
        }

        return $sample;
    }
}
