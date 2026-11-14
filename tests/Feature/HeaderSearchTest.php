<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureCompanyEntitled;
use App\Livewire\Notify\HeaderSearch;
use App\Livewire\Notify\NotificationCenter;
use App\Models\NotifyBatch;
use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\NotifyRule;
use App\Models\NotifyTemplate;
use App\Models\Team;
use App\Models\User;
use App\Support\HeaderSearch as Index;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * R-52: the header search. One question asked of THIS company's messages log, rules, channels, templates, batches and the events the
 * connected apps can send. It must never cross a company, never read a message body, a failure reason or a channel's credentials,
 * and must be on every page of the signed-in area.
 */
class HeaderSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $viewer;

    private Team $team;

    private Team $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withPersonalTeam()->create();
        $this->team = $this->owner->currentTeam;
        $this->viewer = User::factory()->create();
        $this->team->users()->attach($this->viewer, ['role' => 'viewer']);
        $this->viewer->forceFill(['current_team_id' => $this->team->id])->save();
        $this->other = User::factory()->withPersonalTeam()->create()->currentTeam;
    }

    private function channel(Team $team, string $name, array $extra = []): NotifyChannel
    {
        return NotifyChannel::create($extra + ['team_id' => $team->id, 'type' => 'email', 'name' => $name, 'config' => ['password' => 'hunter2-secret'], 'is_active' => true]);
    }

    private function template(Team $team, string $name, string $slug, array $extra = []): NotifyTemplate
    {
        return NotifyTemplate::create($extra + ['team_id' => $team->id, 'name' => $name, 'slug' => $slug, 'subject' => 'Subject of '.$name, 'body' => 'Body of '.$name, 'channel_type' => 'email', 'is_active' => true]);
    }

    private function log(Team $team, string $recipient, string $subject, array $extra = []): NotifyLog
    {
        return NotifyLog::create($extra + [
            'team_id' => $team->id, 'recipient' => $recipient, 'subject' => $subject, 'body' => 'private body text', 'body_preview' => 'preview', 'status' => 'sent',
            'event' => 'finance.invoice.overdue', 'sent_at' => now(),
        ]);
    }

    // ---------------------------------------------------------------- it is on every page

    /** @return array<string, array{string}> */
    public static function pages(): array
    {
        return [
            'dashboard' => ['/dashboard'],
            'connected apps' => ['/connected-apps'],
            'search results' => ['/search?q=anything'],
            'profile' => ['/user/profile'],
            'team settings' => ['/teams/CURRENT'],
            'create team' => ['/teams/create'],
        ];
    }

    #[DataProvider('pages')]
    public function test_the_search_pill_is_in_the_header_of_every_page_of_the_signed_in_area(string $url): void
    {
        $url = str_replace('CURRENT', (string) $this->team->id, $url);
        $html = $this->actingAs($this->owner)->get($url)->assertOk()->getContent();

        $header = substr($html, strpos($html, '<header'), strpos($html, '</header>') - strpos($html, '<header'));
        $this->assertStringContainsString('data-dh-search', $header);
        $this->assertStringContainsString('class="dsr-input"', $header);
        $this->assertStringContainsString('role="search"', $header);
        $this->assertStringContainsString('data-dh-search-toggle', $header, 'a phone gets the search button that opens the sheet');
    }

    public function test_a_guest_is_sent_to_sign_in_and_the_login_page_has_no_company_search(): void
    {
        $this->get('/search?q=invoice')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertDontSee('dsr-input', false);
    }

    // ---------------------------------------------------------------- what it finds

    public function test_it_finds_messages_rules_channels_templates_batches_and_events_of_the_current_company(): void
    {
        $channel = $this->channel($this->team, 'Zulu mailer');
        $template = $this->template($this->team, 'Zulu reminder', 'zulu-reminder');
        NotifyRule::create(['team_id' => $this->team->id, 'template_id' => $template->id, 'channel_id' => $channel->id, 'trigger_event' => 'zulu.event.happened', 'is_active' => true]);
        NotifyBatch::create(['team_id' => $this->team->id, 'template_id' => $template->id, 'name' => 'Zulu batch', 'status' => 'completed', 'total_recipients' => 4, 'sent_count' => 4, 'failed_count' => 0]);
        $log = $this->log($this->team, 'zulu.person@example.test', 'Zulu subject');

        $r = Index::run($this->owner, 'zulu');
        $groups = collect($r['groups'])->keyBy('key');

        $this->assertSame(['log', 'rules', 'channels', 'templates', 'batches'], $groups->keys()->all());
        $this->assertSame('zulu.person@example.test', $groups['log']['items'][0]['title']);
        $this->assertStringContainsString('zulu.event.happened', $groups['rules']['items'][0]['title']);
        $this->assertSame('Zulu mailer', $groups['channels']['items'][0]['title']);
        $this->assertSame('Zulu reminder', $groups['templates']['items'][0]['title']);
        $this->assertSame('Zulu batch', $groups['batches']['items'][0]['title']);

        // The message result is the existing log selection (?log=), the others are the sections that manage them.
        $this->assertSame(route('dashboard', ['log' => $log->id]).'#slip', $groups['log']['items'][0]['href']);
        $this->assertSame(route('dashboard').'#rules', $groups['rules']['items'][0]['href']);
        $this->assertSame(route('dashboard').'#channels', $groups['channels']['items'][0]['href']);
        $this->assertSame(route('dashboard').'#templates', $groups['templates']['items'][0]['href']);

        // A catalogued event (static, nothing queried) opens Connected apps.
        $events = collect(Index::run($this->owner, 'due_soon')['groups'])->keyBy('key');
        $this->assertTrue($events->has('events'));
        $this->assertSame(route('connected-apps'), $events['events']['items'][0]['href']);
    }

    public function test_a_rule_is_found_by_the_name_of_its_template_or_channel(): void
    {
        $channel = $this->channel($this->team, 'Quokka relay');
        $template = $this->template($this->team, 'Wombat letter', 'wombat-letter');
        NotifyRule::create(['team_id' => $this->team->id, 'template_id' => $template->id, 'channel_id' => $channel->id, 'trigger_event' => 'plain.event', 'is_active' => true]);

        foreach (['quokka', 'wombat'] as $term) {
            $rules = collect(Index::run($this->owner, $term)['groups'])->firstWhere('key', 'rules');
            $this->assertNotNull($rules, $term);
            $this->assertSame('plain.event', $rules['items'][0]['title']);
        }
    }

    public function test_a_message_is_found_by_its_status_word(): void
    {
        $this->log($this->team, 'a@example.test', 'One', ['status' => 'failed']);
        $this->log($this->team, 'b@example.test', 'Two', ['status' => 'sent']);

        $titles = collect(collect(Index::run($this->owner, 'failed')['groups'])->firstWhere('key', 'log')['items'])->pluck('title')->all();

        $this->assertSame(['a@example.test'], $titles);
    }

    // ---------------------------------------------------------------- tenant isolation

    public function test_it_never_returns_another_companys_records_whatever_the_page_or_the_widget(): void
    {
        $theirs = $this->channel($this->other, 'Rival mailer');
        $theirTemplate = $this->template($this->other, 'Rival template', 'rival-template');
        NotifyRule::create(['team_id' => $this->other->id, 'template_id' => $theirTemplate->id, 'channel_id' => $theirs->id, 'trigger_event' => 'rival.secret.event', 'is_active' => true]);
        NotifyBatch::create(['team_id' => $this->other->id, 'template_id' => $theirTemplate->id, 'name' => 'Rival batch', 'status' => 'completed', 'total_recipients' => 1, 'sent_count' => 1, 'failed_count' => 0]);
        $theirLog = $this->log($this->other, 'rival.person@example.test', 'Rival confidential subject');
        $this->channel($this->team, 'Our own mailer');

        // The query layer: nothing of theirs for any word that only they hold.
        foreach (['rival', 'confidential', 'rival.secret'] as $term) {
            $this->assertSame([], Index::run($this->owner, $term)['groups'], $term);
        }

        // The full results page and the live widget.
        $this->actingAs($this->owner)->get('/search?q=rival')->assertOk()->assertDontSee('rival.person@example.test')->assertDontSee('Rival mailer')->assertDontSee('rival.secret.event')->assertSee('Nothing found');

        Livewire::actingAs($this->owner)->test(HeaderSearch::class)->set('query', 'rival')->assertDontSee('rival.person@example.test')->assertSee('Nothing in this company matches');

        // And the other company sees only its own (nobody is signed in here, so only the explicit team filter is at work).
        $this->app['auth']->forgetGuards();
        $this->assertNotEmpty(Index::run($this->other->owner, 'rival')['groups']);
        $this->assertSame([], Index::run($this->other->owner, 'our own')['groups']);

        // A result link into another company's log is no way in: the slip shows only this company's rows.
        Livewire::actingAs($this->owner)->withQueryParams(['log' => $theirLog->id])->test(NotificationCenter::class)
            ->assertDontSee('rival.person@example.test')
            ->assertDontSee('Rival confidential subject');
    }

    public function test_a_person_with_no_current_team_gets_nothing_and_the_page_sends_them_to_create_one(): void
    {
        $this->log($this->team, 'x@example.test', 'Something');
        $loner = User::factory()->create();
        $loner->forceFill(['current_team_id' => null])->save();

        $this->assertSame([], Index::run($loner->fresh(), 'something')['groups']);
        $this->actingAs($loner->fresh())->get('/search?q=something')->assertRedirect(route('teams.create'));
    }

    // ---------------------------------------------------------------- what it never reads or shows

    public function test_it_never_searches_or_shows_a_message_body_a_failure_reason_or_channel_credentials(): void
    {
        $this->channel($this->team, 'Plain channel');
        $this->log($this->team, 'plain@example.test', 'Plain subject', [
            'body' => 'zebra-body-secret', 'body_preview' => 'zebra-preview-secret', 'failure_reason' => 'zebra-reason-secret', 'last_error' => 'zebra-error-secret', 'status' => 'failed',
        ]);

        foreach (['zebra-body-secret', 'zebra-preview-secret', 'zebra-reason-secret', 'zebra-error-secret', 'hunter2-secret'] as $term) {
            $this->assertSame([], Index::run($this->owner, $term)['groups'], $term.' is not searchable');
        }

        $html = $this->actingAs($this->owner)->get('/search?q=plain')->assertOk()->getContent();
        foreach (['zebra-body-secret', 'zebra-preview-secret', 'zebra-reason-secret', 'zebra-error-secret', 'hunter2-secret'] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
        $this->assertStringContainsString('plain@example.test', $html);
    }

    // ---------------------------------------------------------------- permissions

    public function test_a_read_only_member_may_search_like_the_rest_of_the_team(): void
    {
        $this->log($this->team, 'viewer.can.see@example.test', 'Visible');

        $this->actingAs($this->viewer)->get('/search?q=visible')->assertOk()->assertSee('viewer.can.see@example.test');
    }

    public function test_the_search_sits_behind_the_same_entitlement_check_as_the_dashboard(): void
    {
        $this->assertContains('search', collect(app('router')->getRoutes()->getRoutes())->filter(
            fn ($route) => in_array(EnsureCompanyEntitled::class, $route->gatherMiddleware(), true)
        )->map(fn ($route) => $route->getName())->all());
    }

    // ---------------------------------------------------------------- states, limits, hostile input

    public function test_empty_short_and_no_match_states(): void
    {
        $this->log($this->team, 'someone@example.test', 'Hello');

        $this->actingAs($this->owner)->get('/search')->assertOk()->assertSee('Type at least 2 letters');
        $this->actingAs($this->owner)->get('/search?q=s')->assertOk()->assertSee('Type at least 2 letters');
        $this->actingAs($this->owner)->get('/search?q=qqqqzzzz')->assertOk()->assertSee('Nothing found')->assertSee('Message bodies are not searched');

        Livewire::actingAs($this->owner)->test(HeaderSearch::class)
            ->assertSee('Type at least 2 letters')
            ->set('query', 'qqqqzzzz')->assertSee('Nothing in this company matches')
            ->set('query', 'someone')->assertSee('someone@example.test')->assertSee('See every result');
    }

    public function test_results_are_limited_per_group_and_the_page_says_how_many_there_are(): void
    {
        foreach (range(1, 30) as $i) {
            $this->log($this->team, "bulk{$i}@example.test", 'Bulk');
        }

        $dropdown = Index::run($this->owner, 'bulk', 4);
        $this->assertCount(4, $dropdown['groups'][0]['items']);
        $this->assertSame(26, $dropdown['groups'][0]['more']);

        $page = Index::run($this->owner, 'bulk', 20);
        $this->assertCount(20, $page['groups'][0]['items']);
        $this->actingAs($this->owner)->get('/search?q=bulk')->assertOk()->assertSee('Showing 20 of 30');
    }

    public function test_wildcards_and_hostile_input_are_harmless(): void
    {
        $this->log($this->team, 'someone@example.test', 'Hello');

        // % and \ carry no meaning: they are dropped, so "%%" is just empty and cannot match everything.
        $this->assertSame([], Index::run($this->owner, '%%')['groups']);
        $this->assertSame('ab', Index::clean('a%b\\'));
        $this->assertSame('a b', Index::clean("  a \n\t b  "));
        $this->assertLessThanOrEqual(Index::MAX_LENGTH, mb_strlen(Index::clean(str_repeat('x', 500))));

        // SQL meant as text is only text.
        $this->assertSame([], Index::run($this->owner, "'; drop table notify_logs; --")['groups']);
        $this->assertSame(1, NotifyLog::count());

        // An array where a string is expected does not break the page.
        $this->actingAs($this->owner)->get('/search?q[]=a&q[]=b')->assertOk();
        // And markup in the query is printed as text.
        $html = $this->actingAs($this->owner)->get('/search?q='.urlencode('<script>alert(1)</script>'))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    // ---------------------------------------------------------------- the log selection a result links to

    public function test_a_result_opens_an_older_message_of_this_company_on_the_slip(): void
    {
        $old = $this->log($this->team, 'oldest@example.test', 'Very old message', ['created_at' => now()->subDays(40)]);
        foreach (range(1, 25) as $i) {
            $this->log($this->team, "new{$i}@example.test", 'Newer', ['created_at' => now()->subMinutes($i)]);
        }

        // It is not one of the 20 newest rows, yet ?log= (what a search result links to) puts it on the slip.
        $component = Livewire::actingAs($this->owner)->withQueryParams(['log' => $old->id])->test(NotificationCenter::class);
        $component->assertSee('oldest@example.test')->assertSee('Very old message');
        $this->assertSame($old->id, $component->instance()->selectedLog()->id);
    }

    public function test_the_search_page_and_the_widget_link_a_message_to_the_log_selection(): void
    {
        $log = $this->log($this->team, 'linked@example.test', 'Linked');

        $this->actingAs($this->owner)->get('/search?q=linked')->assertOk()->assertSee('href="'.route('dashboard', ['log' => $log->id]).'#slip"', false);

        Livewire::actingAs($this->owner)->test(HeaderSearch::class)->set('query', 'linked')
            ->assertSeeHtml('href="'.route('dashboard', ['log' => $log->id]).'#slip"');
    }
}
