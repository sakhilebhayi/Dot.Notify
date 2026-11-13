<?php

namespace Tests\Feature;

use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\NotifyRule;
use App\Models\NotifyTemplate;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The page people arrive at after signing in through InfoDot (/dashboard).
 *
 * It names the person and the company, offers exactly one dominant next step chosen from what the
 * company has really set up, and shows nothing that is not a record: the latest message, today's
 * counts and each channel's own sending history. An owner or administrator is offered the step that
 * changes something; an editor or viewer, who may only read, is never offered one they would be refused.
 */
class LandingExperienceTest extends TestCase
{
    use RefreshDatabase;

    private const LOGO_SHA256 = [
        'images/logo.png' => 'e79be72287fed65fa2b68f5d8b1632e7cbf18a886e5cbc33c4d20a9ec83f1b5f',
        'images/logo-light.png' => '626e5b9dd3f993129454a1ee62c80aeac976d2f32f63f26d75a7b3bce7758dc8',
        'images/mark.png' => '59b7e5d589075b401395551fae9735b2597d3be5a9709488f014d641b5a38cde',
        'images/mark-light.png' => '818f12a49178bcfb7af0c2f47cdc195efba34e6d0218a22a6e8f3142e8248461',
    ];

    private Team $team;

    /** @var array<string, User> */
    private array $people;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->withPersonalTeam()->create(['name' => 'Amara Dlamini']);
        $this->team = $owner->currentTeam;
        $this->team->forceFill(['name' => 'Khanya Logistics'])->save();

        $this->people = ['owner' => $owner];

        foreach (['admin', 'editor', 'viewer'] as $role) {
            $member = User::factory()->create(['name' => ucfirst($role).' Person']);
            $this->team->users()->attach($member, ['role' => $role]);
            $member->forceFill(['current_team_id' => $this->team->id])->save();
            $this->people[$role] = $member->fresh();
        }
    }

    /** @return array<string, array{string}> */
    public static function roles(): array
    {
        return ['owner' => ['owner'], 'admin' => ['admin'], 'editor' => ['editor'], 'viewer' => ['viewer']];
    }

    /** @return array<string, array{string}> */
    public static function managers(): array
    {
        return ['owner' => ['owner'], 'admin' => ['admin']];
    }

    /** @return array<string, array{string}> */
    public static function readers(): array
    {
        return ['editor' => ['editor'], 'viewer' => ['viewer']];
    }

    private function landing(string $role): TestResponse
    {
        return $this->actingAs($this->people[$role])->get('/dashboard')->assertOk();
    }

    /** The one primary action on the page: its label and where it goes. Fails unless there is exactly one. */
    private function primaryAction(TestResponse $response): array
    {
        $html = (string) $response->getContent();

        $this->assertSame(1, substr_count($html, 'data-primary-action'), 'The landing must carry exactly one primary action.');
        $this->assertSame(1, preg_match('/<a class="nf-primary" href="([^"]+)" data-primary-action>([^<]+)<\/a>/', $html, $m));

        return ['url' => html_entity_decode($m[1]), 'label' => trim($m[2])];
    }

    private function channel(bool $active = true, string $name = 'Primary Email'): NotifyChannel
    {
        return NotifyChannel::create(['team_id' => $this->team->id, 'type' => 'email', 'name' => $name, 'is_active' => $active]);
    }

    private function rule(NotifyChannel $channel, bool $active = true): NotifyRule
    {
        $template = NotifyTemplate::create(['team_id' => $this->team->id, 'name' => 'Task assigned', 'subject' => 'A task for you', 'body' => 'Hello', 'channel_type' => 'email']);

        return NotifyRule::create(['team_id' => $this->team->id, 'trigger_event' => 'task.assigned', 'template_id' => $template->id, 'channel_id' => $channel->id, 'is_active' => $active]);
    }

    private function log(string $status, ?NotifyChannel $channel = null, array $extra = []): NotifyLog
    {
        return NotifyLog::create($extra + [
            'team_id' => $this->team->id,
            'channel_id' => $channel?->id,
            'recipient' => $status.'@khanya.test',
            'subject' => 'Subject for '.$status,
            'status' => $status,
            'sent_at' => $status === 'sent' ? now() : null,
        ]);
    }

    #[DataProvider('roles')]
    public function test_it_welcomes_the_person_and_the_company_by_name_with_the_real_logo(string $role): void
    {
        $response = $this->landing($role);

        $response->assertSeeText('Make sure the right person is told.');
        $response->assertSeeText('Welcome, '.explode(' ', $this->people[$role]->name)[0].'.');
        $response->assertSeeText('Khanya Logistics');
        $response->assertSee('src="'.asset('images/logo.png').'" alt="Dot.Notify"', false);
        $response->assertSee('src="'.asset('images/logo-light.png').'" alt="Dot.Notify"', false);
        $this->primaryAction($response);
    }

    public function test_the_logo_files_are_the_originals(): void
    {
        foreach (self::LOGO_SHA256 as $file => $hash) {
            $this->assertFileExists(public_path($file));
            $this->assertSame($hash, hash_file('sha256', public_path($file)), "{$file} must not be altered.");
        }
    }

    public function test_the_way_back_to_infodot_uses_the_configured_infodot_address(): void
    {
        config(['ecosystem.platforms.infodot.url' => 'https://infodot.co.za/']);

        $this->landing('owner')
            ->assertSee('<a href="https://infodot.co.za/home">InfoDot</a>', false)
            ->assertSee('<a href="https://infodot.co.za/platforms">Platforms</a>', false);
    }

    #[DataProvider('managers')]
    public function test_with_no_channel_an_owner_or_admin_is_sent_to_add_one_and_the_form_is_open(string $role): void
    {
        $response = $this->landing($role);
        $action = $this->primaryAction($response);

        $this->assertSame('Add a channel', $action['label']);
        $this->assertSame(route('dashboard', ['start' => 'channel']).'#channels', $action['url']);
        $response->assertSeeText('Khanya Logistics has no channel yet, so nothing can be sent.');
        $response->assertDontSee('wire:submit="addChannel"', false);

        // The target answers, and arrives with the form already open.
        $this->actingAs($this->people[$role])->get($action['url'])
            ->assertOk()
            ->assertSee('wire:submit="addChannel"', false)
            ->assertSee('id="channel-name"', false);
    }

    #[DataProvider('readers')]
    public function test_with_no_channel_a_read_only_member_is_shown_what_can_be_sent_and_never_a_form(string $role): void
    {
        $response = $this->landing($role);
        $action = $this->primaryAction($response);

        $this->assertSame('See what can be sent', $action['label']);
        $this->assertSame(route('connected-apps'), $action['url']);
        $response->assertSeeText('Only an owner or administrator of Khanya Logistics can change it.');

        $this->actingAs($this->people[$role])->get($action['url'])->assertOk();

        // Asking for the form by hand does not open it for someone who may not use it.
        $this->actingAs($this->people[$role])->get(route('dashboard', ['start' => 'channel']))
            ->assertOk()
            ->assertDontSee('wire:submit="addChannel"', false);
    }

    #[DataProvider('roles')]
    public function test_with_a_channel_and_no_rule_switched_on_the_next_step_is_connected_apps(string $role): void
    {
        $this->rule($this->channel(), active: false);

        $response = $this->landing($role);
        $action = $this->primaryAction($response);

        $this->assertSame(in_array($role, ['owner', 'admin'], true) ? 'Switch on events' : 'See what can be sent', $action['label']);
        $this->assertSame(route('connected-apps'), $action['url']);
        $response->assertSeeText('No rule is switched on, so nothing is sent yet.');
        $this->actingAs($this->people[$role])->get($action['url'])->assertOk();
    }

    #[DataProvider('roles')]
    public function test_when_every_channel_is_switched_off_it_says_so(string $role): void
    {
        $this->rule($this->channel(active: false));

        $response = $this->landing($role);
        $action = $this->primaryAction($response);

        $response->assertSeeText('Your one channel is switched off, so nothing can be sent.');
        $this->assertSame(
            in_array($role, ['owner', 'admin'], true) ? route('dashboard').'#channels' : route('connected-apps'),
            $action['url'],
        );
        $this->actingAs($this->people[$role])->get($action['url'])->assertOk();
    }

    #[DataProvider('roles')]
    public function test_a_company_that_is_sending_is_taken_to_its_log(string $role): void
    {
        $channel = $this->channel();
        $this->rule($channel);
        $this->log('sent', $channel);

        $response = $this->landing($role);
        $action = $this->primaryAction($response);

        $this->assertSame('Read the log', $action['label']);
        $this->assertSame(route('dashboard').'#log', $action['url']);
        $response->assertSeeText('1 message sent today, none failed.');
        $response->assertSee('id="log"', false);
        $this->actingAs($this->people[$role])->get($action['url'])->assertOk();
    }

    #[DataProvider('roles')]
    public function test_a_failure_today_becomes_the_next_step_and_opens_the_log_on_failed_messages(string $role): void
    {
        $channel = $this->channel();
        $this->rule($channel);
        $this->log('sent', $channel);
        $this->log('failed', $channel);

        $response = $this->landing($role);
        $action = $this->primaryAction($response);

        $this->assertSame('Review failed messages', $action['label']);
        $this->assertSame(route('dashboard', ['status' => 'failed']).'#log', $action['url']);
        $response->assertSeeText('1 message failed today.');

        $filtered = $this->actingAs($this->people[$role])->get($action['url'])->assertOk();
        $log = substr((string) $filtered->getContent(), strpos((string) $filtered->getContent(), 'id="log"'));
        $log = substr($log, 0, strpos($log, 'id="batches"'));

        $this->assertStringContainsString('failed@khanya.test', $log);
        $this->assertStringNotContainsString('sent@khanya.test', $log);
    }

    public function test_a_status_the_log_does_not_have_is_ignored(): void
    {
        $channel = $this->channel();
        $this->log('sent', $channel);

        $this->actingAs($this->people['owner'])->get('/dashboard?status=opened')
            ->assertOk()
            ->assertViewHas('logStatus', '')
            ->assertSee('sent@khanya.test');
    }

    public function test_a_company_with_nothing_shows_an_honest_empty_state_and_only_zeros(): void
    {
        $response = $this->landing('owner');

        $response->assertSeeText('Nothing has been handed over for Khanya Logistics yet.');
        $response->assertSeeText('No notifications sent yet.');
        $response->assertSeeText('No bulk sends yet.');
        $response->assertSeeText('No channels configured yet.');
        $response->assertSeeText('No templates yet.');
        $response->assertSeeText('No routing rules yet.');
        $response->assertDontSee('class="nf-ticks"', false);

        $this->assertSame(['0', '0', '0', '0', '0'], $this->figures($response));
    }

    public function test_every_figure_on_the_page_comes_from_the_companys_own_records(): void
    {
        $email = $this->channel();
        $this->channel(active: false, name: 'Ops Slack');
        $this->rule($email);

        $this->log('sent', $email, ['recipient' => 'one@khanya.test']);
        $this->log('sent', $email, ['recipient' => 'two@khanya.test']);
        $this->log('sent', $email, ['recipient' => 'three@khanya.test']);
        $this->log('failed', $email);
        $this->log('retrying', $email);
        $this->log('retrying', $email, ['recipient' => 'again@khanya.test']);
        $this->log('suppressed', $email);

        // Another company's records must not move any figure.
        $other = User::factory()->withPersonalTeam()->create()->currentTeam;
        $theirs = NotifyChannel::create(['team_id' => $other->id, 'type' => 'email', 'name' => 'Not Ours', 'is_active' => true]);
        NotifyLog::create(['team_id' => $other->id, 'channel_id' => $theirs->id, 'recipient' => 'stranger@other.test', 'status' => 'sent', 'sent_at' => now()]);
        NotifyLog::create(['team_id' => $other->id, 'channel_id' => $theirs->id, 'recipient' => 'stranger2@other.test', 'status' => 'failed']);

        $response = $this->landing('viewer');

        // sent today, failed today, being retried, held back in 30 days, channels on (of 2)
        $this->assertSame(['3', '1', '2', '1', '1 of 2'], $this->figures($response));
        $response->assertSeeText('3 sent, the last');
        $response->assertSeeText('Email. Nothing sent through it yet.');
        $response->assertDontSee('Not Ours');
        $response->assertDontSee('stranger@other.test');
    }

    public function test_the_latest_message_is_shown_as_a_slip_with_only_its_own_state_ticked(): void
    {
        $channel = $this->channel();
        $this->rule($channel);
        $this->log('sent', $channel, ['recipient' => 'older@khanya.test', 'created_at' => now()->subHour()]);
        $this->log('retrying', $channel, ['recipient' => 'newest@khanya.test', 'subject' => 'Load manifest', 'event' => 'task.assigned', 'body' => 'private body text']);

        $html = (string) $this->landing('editor')->getContent();
        $slip = substr($html, strpos($html, 'class="nf-slip"'));
        $slip = substr($slip, 0, strpos($slip, '</section>'));

        $this->assertStringContainsString('newest@khanya.test', $slip);
        $this->assertStringContainsString('Load manifest', $slip);
        $this->assertStringContainsString('task.assigned', $slip);
        $this->assertStringContainsString('Primary Email', $slip);
        $this->assertStringNotContainsString('older@khanya.test', $slip);
        $this->assertStringNotContainsString('private body text', $html);

        $this->assertSame(1, substr_count($slip, 'is-on'));
        $this->assertSame(1, preg_match('/<li class="nf-tick is-on"[^>]*>.*?<\/span>\s*Retrying\s*<\/li>/s', $slip));
        $this->assertStringContainsString('It will be tried again automatically.', $slip);
    }

    public function test_a_channel_says_what_it_has_sent_and_one_that_never_sent_says_so(): void
    {
        $used = $this->channel();
        $this->channel(name: 'Standby Email');
        $this->log('sent', $used, ['sent_at' => now()->subHours(2)]);
        $this->log('failed', $used);

        $this->landing('owner')
            ->assertSeeText('1 sent, the last 2 hours ago.')
            ->assertSeeText('Email. Nothing sent through it yet.');
    }

    public function test_everything_the_old_page_offered_is_still_on_the_page(): void
    {
        $response = $this->landing('owner');

        foreach (['Notification Log', 'Recent Batches', 'Channels', 'Templates', 'Routing Rules'] as $heading) {
            $response->assertSeeText($heading);
        }

        foreach (['log', 'batches', 'channels', 'templates', 'rules'] as $anchor) {
            $response->assertSee('id="'.$anchor.'"', false);
            $response->assertSee('href="#'.$anchor.'"', false);
        }

        $response->assertSee('href="'.route('connected-apps').'"', false);
        $response->assertSee('+ Add Channel')->assertSee('AI Generate')->assertSee('+ Add Rule');
    }

    public function test_the_shell_and_the_home_page_load_no_font_or_icon_from_another_host(): void
    {
        $html = (string) $this->landing('owner')->getContent();

        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringNotContainsString('fonts.gstatic.com', $html);
        $this->assertStringNotContainsString('material-symbols', $html);
        // Between <main> and </main> is the home page itself (the banner above it is Jetstream's own component).
        $main = substr($html, strpos($html, '<main>'), strpos($html, '</main>') - strpos($html, '<main>'));
        $this->assertStringNotContainsString(' style="', $main, 'The home page is styled by the design system, not inline styles.');
        $this->assertStringNotContainsString(' onclick=', $html);

        $css = (string) file_get_contents(resource_path('css/notify.css'));
        $this->assertStringNotContainsString('http', $css);
        $this->assertFileExists(resource_path('fonts/atkinson-hyperlegible-next-latin-wght-normal.woff2'));
    }

    public function test_the_platform_list_holds_only_current_platforms(): void
    {
        $current = [
            'agents', 'analytics', 'auction', 'central', 'charts', 'design', 'docs', 'dopemine', 'ehail', 'emall', 'engage', 'files',
            'finance', 'forms', 'hr', 'memory', 'mines', 'notify', 'press', 'projects', 'pulse', 'sheet', 'tasks', 'tutor', 'infodot',
        ];

        $this->assertSame([], array_values(array_diff(array_keys(config('ecosystem.platforms')), $current)));
        $this->assertSame([], array_values(array_diff(array_keys(config('notify_events.platforms')), $current)));
    }

    /**
     * The figures in "the record so far", in page order.
     *
     * @return list<string>
     */
    private function figures(TestResponse $response): array
    {
        $html = (string) $response->getContent();
        $band = substr($html, strpos($html, 'class="nf-today"'));
        $band = substr($band, 0, strpos($band, '</dl>'));

        preg_match_all('/<dd>(.*?)<\/dd>/s', $band, $m);

        return array_map(fn (string $dd) => trim(preg_replace('/\s+/', ' ', strip_tags($dd))), $m[1]);
    }
}
