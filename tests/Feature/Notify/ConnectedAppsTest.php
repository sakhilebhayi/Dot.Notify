<?php

namespace Tests\Feature\Notify;

use App\Livewire\Notify\ConnectedApps;
use App\Models\NotifyChannel;
use App\Models\NotifyInboundEvent;
use App\Models\NotifyRule;
use App\Models\NotifyTemplate;
use App\Models\Team;
use App\Models\User;
use App\Services\Notify\EventEnabler;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * "Connected apps": which events each Dot app can send, which ones this company
 * has switched on, and when each last arrived. Owner and admin switch events;
 * editors and viewers see the state read-only.
 */
class ConnectedAppsTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    /** @var array<string, User> */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->withPersonalTeam()->create();
        $this->team = $owner->currentTeam;
        $this->users['owner'] = $owner;

        foreach (['admin', 'editor', 'viewer'] as $role) {
            $user = User::factory()->create();
            $this->team->users()->attach($user, ['role' => $role]);
            $user->switchTeam($this->team);
            $this->users[$role] = $user->fresh();
        }

        $this->users['outsider'] = User::factory()->withPersonalTeam()->create();
        NotifyChannel::create(['team_id' => $this->team->id, 'type' => 'email', 'name' => 'Primary Email']);
    }

    private function rules(?Team $team = null): Collection
    {
        return NotifyRule::withoutGlobalScopes()->where('team_id', ($team ?? $this->team)->id)->get();
    }

    private function inbound(string $platform, string $event, string $status, \DateTimeInterface $at, ?Team $team = null): NotifyInboundEvent
    {
        $row = NotifyInboundEvent::withoutGlobalScopes()->create([
            'team_id' => ($team ?? $this->team)->id,
            'platform' => $platform,
            'idempotency_key' => $event.':'.uniqid(),
            'payload' => ['event' => $event],
            'source_event' => $event,
            'trigger_event' => $event,
            'verified_at' => $at,
            'status' => $status,
        ]);
        NotifyInboundEvent::withoutGlobalScopes()->whereKey($row->id)->update(['created_at' => $at]);

        return $row;
    }

    /** @return array<string, array{string, bool}> */
    public static function roles(): array
    {
        return [
            'owner' => ['owner', true],
            'admin' => ['admin', true],
            'editor' => ['editor', false],
            'viewer' => ['viewer', false],
        ];
    }

    #[DataProvider('roles')]
    public function test_only_owner_and_admin_may_switch_events(string $role, bool $mayManage): void
    {
        $this->assertSame($mayManage, Gate::forUser($this->users[$role])->allows('create', [NotifyRule::class, $this->team]));
    }

    public function test_the_page_needs_a_signed_in_member_with_a_team(): void
    {
        $this->get('/connected-apps')->assertRedirect('/login');

        $teamless = User::factory()->create();
        $this->actingAs($teamless)->get('/connected-apps')->assertRedirect(route('teams.create'));
    }

    #[DataProvider('roles')]
    public function test_every_member_sees_every_platform_and_event_with_its_description(string $role): void
    {
        $this->actingAs($this->users[$role])->get('/connected-apps')
            ->assertOk()
            ->assertSeeText('Connected apps')
            ->assertSeeText('Dot.Tasks')
            ->assertSeeText('Dot.HR')
            ->assertSeeText('Dot.Auction')
            ->assertSeeText('Dot.Ehail')
            ->assertSeeText('Dot.Farms')
            ->assertSeeText('Dot.Billing')
            ->assertSee('task.assigned')
            ->assertSee('invoice.overdue')
            ->assertSee('leave.requested')
            ->assertSeeText('Task assigned');
    }

    public function test_owner_and_admin_see_a_switch_for_each_event_and_nobody_else_does(): void
    {
        foreach (['owner', 'admin'] as $role) {
            $html = $this->actingAs($this->users[$role])->get('/connected-apps')->assertOk()->getContent();
            $this->assertSame(16, substr_count($html, 'role="switch"'), "{$role} should get one switch per event.");
        }

        foreach (['editor', 'viewer'] as $role) {
            $html = $this->actingAs($this->users[$role])->get('/connected-apps')->assertOk()->getContent();
            $this->assertStringNotContainsString('role="switch"', $html);
            $this->assertStringNotContainsString('wire:click="toggle(\'', $html);
            $this->assertStringNotContainsString('wire:click="attachChannel', $html);
            $this->assertStringContainsString('Off', strip_tags($html));
        }
    }

    public function test_the_owner_can_switch_an_event_on_and_off(): void
    {
        Livewire::actingAs($this->users['owner'])->test(ConnectedApps::class)
            ->call('toggle', 'task.assigned')
            ->assertSee('Task assigned is on');

        $rule = $this->rules()->sole();
        $this->assertSame('task.assigned', $rule->trigger_event);
        $this->assertTrue($rule->is_active);
        $this->assertNotNull($rule->template_id);
        $this->assertNotNull($rule->channel_id);

        Livewire::actingAs($this->users['owner'])->test(ConnectedApps::class)
            ->call('toggle', 'task.assigned')
            ->assertSee('Task assigned is off');

        $this->assertFalse($this->rules()->sole()->is_active);
        $this->assertSame(1, NotifyTemplate::withoutGlobalScopes()->where('team_id', $this->team->id)->count());
    }

    public function test_an_admin_can_toggle_too(): void
    {
        Livewire::actingAs($this->users['admin'])->test(ConnectedApps::class)->call('toggle', 'ride.expired');

        $this->assertTrue($this->rules()->sole()->is_active);
    }

    #[DataProvider('readOnlyRoles')]
    public function test_a_crafted_toggle_from_an_editor_or_viewer_is_forbidden_and_changes_nothing(string $role): void
    {
        Livewire::actingAs($this->users[$role])->test(ConnectedApps::class)
            ->call('toggle', 'task.assigned')
            ->assertForbidden();

        $this->assertCount(0, $this->rules());
        $this->assertSame(0, NotifyTemplate::withoutGlobalScopes()->where('team_id', $this->team->id)->count());
    }

    /** @return array<string, array{string}> */
    public static function readOnlyRoles(): array
    {
        return ['editor' => ['editor'], 'viewer' => ['viewer']];
    }

    public function test_a_crafted_toggle_cannot_switch_an_event_that_is_not_in_the_catalogue(): void
    {
        Livewire::actingAs($this->users['owner'])->test(ConnectedApps::class)
            ->call('toggle', 'made.up_event')
            ->assertNotFound();

        $this->assertCount(0, $this->rules());
    }

    public function test_an_outsider_toggle_only_ever_touches_their_own_company(): void
    {
        $outsider = $this->users['outsider'];
        NotifyChannel::create(['team_id' => $outsider->currentTeam->id, 'type' => 'email', 'name' => 'Theirs']);

        Livewire::actingAs($outsider)->test(ConnectedApps::class)->call('toggle', 'task.assigned');

        $this->assertCount(0, $this->rules());
        $this->assertCount(1, $this->rules($outsider->currentTeam));
    }

    public function test_the_page_shows_the_state_of_this_company_only(): void
    {
        NotifyChannel::create(['team_id' => $this->users['outsider']->currentTeam->id, 'type' => 'email', 'name' => 'Theirs']);
        app(EventEnabler::class)->enable($this->users['outsider']->currentTeam, 'tasks', 'task.assigned');

        $component = Livewire::actingAs($this->users['owner'])->test(ConnectedApps::class);

        $this->assertSame('off', $component->instance()->apps()['tasks']['events']['task.assigned']['state']);
    }

    public function test_it_shows_when_each_event_last_arrived_for_this_company_and_what_happened_to_it(): void
    {
        app(EventEnabler::class)->enable($this->team, 'tasks', 'task.assigned');
        $this->inbound('tasks', 'task.assigned', 'routed', now()->subDays(3));
        $this->inbound('tasks', 'task.assigned', 'routed', now()->subHours(2));
        $this->inbound('tasks', 'task.due_soon', 'no_rule', now()->subMinutes(10));
        // Another company's traffic, and a wrong-platform event of the same name, must not show up.
        $this->inbound('tasks', 'task.mentioned', 'routed', now()->subMinute(), $this->users['outsider']->currentTeam);
        $this->inbound('billing', 'task.escalated', 'no_rule', now()->subMinute());

        $html = $this->actingAs($this->users['viewer'])->get('/connected-apps')->assertOk()->getContent();
        $apps = Livewire::actingAs($this->users['viewer'])->test(ConnectedApps::class)->instance()->apps();

        $assigned = $apps['tasks']['events']['task.assigned']['last'];
        $this->assertSame('routed', $assigned['status']);
        $this->assertTrue($assigned['at']->between(now()->subHours(2)->subMinute(), now()->subHours(2)->addMinute()));
        $this->assertSame('no_rule', $apps['tasks']['events']['task.due_soon']['last']['status']);
        $this->assertNull($apps['tasks']['events']['task.mentioned']['last']);
        $this->assertNull($apps['tasks']['events']['task.escalated']['last']);

        $this->assertStringContainsString('>2 hours ago</time>', $html);
        $this->assertStringContainsString('>10 minutes ago</time>', $html);
        $this->assertStringContainsString('Queued for delivery', $html);
        $this->assertStringContainsString('Received, but no rule is on so nothing was sent', $html);
        $this->assertStringContainsString('Never received', $html);
    }

    public function test_a_company_with_no_email_channel_sees_a_clear_flag_not_a_green_light(): void
    {
        NotifyChannel::withoutGlobalScopes()->where('team_id', $this->team->id)->delete();

        Livewire::actingAs($this->users['owner'])->test(ConnectedApps::class)
            ->call('toggle', 'task.assigned')
            ->assertSee('No email channel yet')
            ->assertSee('Task assigned is on, but nothing will be sent until you add an email channel');

        $this->actingAs($this->users['viewer'])->get('/connected-apps')->assertOk()->assertSeeText('No email channel yet');
    }

    public function test_the_owner_can_attach_a_channel_added_after_switching_an_event_on(): void
    {
        NotifyChannel::withoutGlobalScopes()->where('team_id', $this->team->id)->delete();
        $component = Livewire::actingAs($this->users['owner'])->test(ConnectedApps::class)->call('toggle', 'task.assigned');
        $this->assertNull($this->rules()->sole()->channel_id);

        $channel = NotifyChannel::create(['team_id' => $this->team->id, 'type' => 'email', 'name' => 'Late Email']);
        $component->call('attachChannel', 'task.assigned');

        $this->assertSame($channel->id, $this->rules()->sole()->channel_id);
    }

    public function test_the_attach_action_is_owner_and_admin_only(): void
    {
        Livewire::actingAs($this->users['viewer'])->test(ConnectedApps::class)->call('attachChannel', 'task.assigned')->assertForbidden();
    }

    public function test_a_platform_without_an_ingest_secret_says_it_cannot_send_yet(): void
    {
        config(['notify.ingest.secrets.tasks' => '', 'notify.ingest.secrets.hr' => 'configured-secret']);

        $html = $this->actingAs($this->users['owner'])->get('/connected-apps')->assertOk()->getContent();
        $apps = Livewire::actingAs($this->users['owner'])->test(ConnectedApps::class)->instance()->apps();

        $this->assertFalse($apps['tasks']['connected']);
        $this->assertTrue($apps['hr']['connected']);
        $this->assertStringContainsString('not connected to Dot.Notify yet', $html);
    }

    public function test_the_page_never_prints_ingest_secrets(): void
    {
        config(['notify.ingest.secrets.tasks' => 'super-secret-signing-value']);

        $this->actingAs($this->users['owner'])->get('/connected-apps')->assertOk()->assertDontSee('super-secret-signing-value');
    }

    public function test_the_sidebar_links_to_connected_apps(): void
    {
        $this->actingAs($this->users['viewer'])->get('/dashboard')->assertOk()->assertSee(route('connected-apps'), false);
    }

    public function test_the_page_lists_the_data_each_event_can_carry(): void
    {
        $this->actingAs($this->users['viewer'])->get('/connected-apps')->assertOk()
            ->assertSee('assigned_by')
            ->assertSee('invoice_url');
    }

    public function test_a_read_only_member_sees_the_no_channel_flag_but_not_the_attach_control(): void
    {
        NotifyChannel::withoutGlobalScopes()->where('team_id', $this->team->id)->delete();
        app(EventEnabler::class)->enable($this->team, 'tasks', 'task.assigned');

        $html = $this->actingAs($this->users['editor'])->get('/connected-apps')->assertOk()->getContent();

        $this->assertStringContainsString('No email channel yet', $html);
        $this->assertStringNotContainsString('attachChannel', $html);

        $owner = $this->actingAs($this->users['owner'])->get('/connected-apps')->assertOk()->getContent();
        $this->assertStringContainsString('attachChannel', $owner);
    }
}
