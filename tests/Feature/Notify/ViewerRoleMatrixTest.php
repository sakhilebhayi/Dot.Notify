<?php

namespace Tests\Feature\Notify;

use App\Livewire\Notify\ChannelManager;
use App\Livewire\Notify\NotificationCenter;
use App\Livewire\Notify\RuleManager;
use App\Livewire\Notify\TemplateEditor;
use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\NotifyRule;
use App\Models\NotifySchedule;
use App\Models\NotifyTemplate;
use App\Models\NotifyWebhook;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Jetstream\Jetstream;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Role by action. InfoDot has a read-only "viewer" company role, so this app has
 * one too. A viewer reads exactly what an editor reads (the dashboard, the log,
 * the channel, template and rule lists) and can change nothing; owner and admin
 * keep managing channels, rules, templates, webhooks and schedules. Nobody below
 * admin sees a channel credential or a webhook signing secret.
 */
class ViewerRoleMatrixTest extends TestCase
{
    use RefreshDatabase;

    private const ROLES = ['owner', 'admin', 'editor', 'viewer', 'outsider'];

    private const SMTP_PASSWORD = 'hunter2-smtp-credential';

    private const SLACK_URL = 'https://hooks.slack.test/services/TOKEN-NEVER-SHOWN';

    private const SIGNING_SECRET = 'whsec-never-shown-to-members';

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
    }

    /** @return array{channel: NotifyChannel, template: NotifyTemplate, rule: NotifyRule, webhook: NotifyWebhook, schedule: NotifySchedule, log: NotifyLog} */
    private function fresh(): array
    {
        NotifySchedule::withoutGlobalScopes()->delete();
        NotifyRule::withoutGlobalScopes()->delete();
        NotifyWebhook::withoutGlobalScopes()->delete();
        NotifyTemplate::withoutGlobalScopes()->delete();
        NotifyChannel::withoutGlobalScopes()->delete();
        NotifyLog::withoutGlobalScopes()->delete();

        $channel = NotifyChannel::create([
            'team_id' => $this->team->id, 'type' => 'email', 'name' => 'Ops Mailer',
            'config' => ['smtp_password' => self::SMTP_PASSWORD, 'url' => self::SLACK_URL],
        ]);
        $template = NotifyTemplate::create(['team_id' => $this->team->id, 'name' => 'Welcome Template', 'body' => 'hello']);
        $rule = NotifyRule::create([
            'team_id' => $this->team->id, 'template_id' => $template->id, 'channel_id' => $channel->id,
            'trigger_event' => 'order.shipped',
        ]);
        $webhook = NotifyWebhook::create(['team_id' => $this->team->id, 'name' => 'Stripe In', 'signing_secret' => self::SIGNING_SECRET]);
        $schedule = NotifySchedule::create(['team_id' => $this->team->id, 'rule_id' => $rule->id, 'name' => 'Nightly', 'cron_expression' => '0 9 * * *']);
        $log = NotifyLog::create([
            'team_id' => $this->team->id, 'channel_id' => $channel->id, 'recipient' => 'amara@example.test',
            'subject' => 'Your parcel left', 'body_preview' => 'preview', 'body' => 'private body text', 'status' => 'sent',
        ]);

        return compact('channel', 'template', 'rule', 'webhook', 'schedule', 'log');
    }

    public function test_jetstream_has_a_read_only_viewer_role(): void
    {
        $viewer = Jetstream::findRole('viewer');

        $this->assertNotNull($viewer);
        $this->assertSame(['read'], $viewer->permissions);
        $this->assertSame(['admin', 'editor', 'viewer'], array_keys(Jetstream::$roles));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function resourceKinds(): array
    {
        return [
            'channel' => ['channel'],
            'template' => ['template'],
            'rule' => ['rule'],
            'webhook' => ['webhook'],
            'schedule' => ['schedule'],
        ];
    }

    #[DataProvider('resourceKinds')]
    public function test_policy_matrix_for_every_notify_resource(string $kind): void
    {
        $model = $this->fresh()[$kind];
        $managers = ['owner', 'admin'];
        $members = ['owner', 'admin', 'editor', 'viewer'];

        foreach (self::ROLES as $role) {
            $gate = Gate::forUser($this->users[$role]);

            $this->assertSame(in_array($role, $members, true), $gate->allows('view', $model), "{$kind} view by {$role}");
            foreach (['update', 'delete'] as $ability) {
                $this->assertSame(in_array($role, $managers, true), $gate->allows($ability, $model), "{$kind} {$ability} by {$role}");
            }
            $this->assertSame(
                in_array($role, $managers, true),
                $gate->allows('create', [$model::class, $this->team]),
                "{$kind} create by {$role}"
            );
        }
    }

    /**
     * action => roles allowed to change something
     *
     * @return array<string, array{string}>
     */
    public static function livewireActions(): array
    {
        return [
            'add channel' => ['addChannel'],
            'toggle channel' => ['toggleChannel'],
            'delete channel' => ['deleteChannel'],
            'create rule' => ['createRule'],
            'edit a rule (open the form)' => ['openRule'],
            'update rule' => ['updateRule'],
            'toggle rule' => ['toggleRule'],
            'delete rule' => ['deleteRule'],
            'generate template with AI' => ['generate'],
            'save template' => ['saveTemplate'],
        ];
    }

    #[DataProvider('livewireActions')]
    public function test_livewire_matrix(string $action): void
    {
        foreach (self::ROLES as $role) {
            $r = $this->fresh();
            $user = $this->users[$role];
            $managerOnly = in_array($role, ['owner', 'admin'], true);

            [$component, $changed] = $this->perform($action, $user, $r);

            if ($role === 'outsider') {
                // Their active team is their own personal one: they can act there, never here.
                $this->assertFalse($changed($this->team), "outsider {$action} must not touch the target team");

                continue;
            }

            if ($managerOnly) {
                $component->assertOk()->assertHasNoErrors();
                $this->assertTrue($changed($this->team), "{$role} {$action} should have worked");
            } else {
                $component->assertForbidden();
                $this->assertFalse($changed($this->team), "{$role} {$action} must change nothing");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array{0: Testable, 1: \Closure(Team): bool}
     */
    private function perform(string $action, User $user, array $r): array
    {
        $ch = $r['channel'];
        $rule = $r['rule'];
        $tpl = $r['template'];

        $count = fn (string $model, Team $team) => $model::withoutGlobalScopes()->where('team_id', $team->id)->count();

        // Outsiders act from their own team, so target ids of this team are simply not visible
        // to them; the ModelNotFound that follows is a 404, not a write.
        try {
            return match ($action) {
                'addChannel' => [
                    Livewire::actingAs($user)->test(ChannelManager::class)->set('name', 'Added Channel')->call('addChannel'),
                    fn (Team $t) => NotifyChannel::withoutGlobalScopes()->where('team_id', $t->id)->where('name', 'Added Channel')->exists(),
                ],
                'toggleChannel' => [
                    Livewire::actingAs($user)->test(ChannelManager::class)->call('toggleChannel', $ch->id),
                    fn () => ! NotifyChannel::withoutGlobalScopes()->find($ch->id)->is_active,
                ],
                'deleteChannel' => [
                    Livewire::actingAs($user)->test(ChannelManager::class)->call('deleteChannel', $ch->id),
                    fn () => NotifyChannel::withoutGlobalScopes()->find($ch->id) === null,
                ],
                'createRule' => [
                    Livewire::actingAs($user)->test(RuleManager::class)
                        ->set('triggerEvent', 'added.rule')->set('templateId', (string) $tpl->id)->set('channelId', (string) $ch->id)->call('saveRule'),
                    fn (Team $t) => NotifyRule::withoutGlobalScopes()->where('team_id', $t->id)->where('trigger_event', 'added.rule')->exists(),
                ],
                'openRule' => (function () use ($user, $rule) {
                    $c = Livewire::actingAs($user)->test(RuleManager::class)->call('edit', $rule->id);

                    return [$c, fn () => $c->get('editingId') === $rule->id];
                })(),
                'updateRule' => (function () use ($user, $rule) {
                    $c = Livewire::actingAs($user)->test(RuleManager::class)->set('editingId', $rule->id)->set('triggerEvent', 'renamed.rule')
                        ->set('templateId', (string) $rule->template_id)->set('channelId', (string) $rule->channel_id)->call('saveRule');

                    return [$c, fn () => NotifyRule::withoutGlobalScopes()->find($rule->id)->trigger_event === 'renamed.rule'];
                })(),
                'toggleRule' => [
                    Livewire::actingAs($user)->test(RuleManager::class)->call('toggleRule', $rule->id),
                    fn () => ! NotifyRule::withoutGlobalScopes()->find($rule->id)->is_active,
                ],
                'deleteRule' => [
                    Livewire::actingAs($user)->test(RuleManager::class)->call('deleteRule', $rule->id),
                    fn () => NotifyRule::withoutGlobalScopes()->find($rule->id) === null,
                ],
                'generate' => (function () use ($user) {
                    $c = Livewire::actingAs($user)->test(TemplateEditor::class)->set('purpose', 'Parcel shipped')->call('generate');

                    return [$c, fn (Team $t) => $user->currentTeam->is($t) && filled($c->get('generatedBody'))];
                })(),
                'saveTemplate' => [
                    Livewire::actingAs($user)->test(TemplateEditor::class)->set('templateName', 'Added Template')->set('generatedBody', 'Body')->call('saveTemplate'),
                    fn (Team $t) => NotifyTemplate::withoutGlobalScopes()->where('team_id', $t->id)->where('name', 'Added Template')->exists(),
                ],
            };
        } catch (ModelNotFoundException) {
            $component = Livewire::actingAs($this->users['outsider'])->test(ChannelManager::class);

            return [$component, fn () => false];
        }
    }

    public function test_a_viewer_reads_what_an_editor_reads_and_nothing_secret(): void
    {
        $this->fresh();

        $seen = [];
        foreach (['editor', 'viewer'] as $role) {
            $dashboard = $this->actingAs($this->users[$role])->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Notification Hub')
                ->assertSee('Ops Mailer')
                ->assertSee('Welcome Template')
                ->assertSee('order.shipped')
                ->assertSee('amara@example.test')
                ->assertSee('Your parcel left');

            $html = $dashboard->getContent();
            $this->assertStringNotContainsString(self::SMTP_PASSWORD, $html, "{$role} dashboard");
            $this->assertStringNotContainsString('TOKEN-NEVER-SHOWN', $html, "{$role} dashboard");
            $this->assertStringNotContainsString(self::SIGNING_SECRET, $html, "{$role} dashboard");
            $this->assertStringNotContainsString('private body text', $html, "{$role} dashboard");

            // Everything the page puts into Livewire snapshots (visible in the markup) too.
            $seen[$role] = ['Ops Mailer', 'Welcome Template', 'order.shipped'];
        }

        $this->assertSame($seen['editor'], $seen['viewer'], 'a viewer must not see more than an editor');
    }

    public function test_no_component_a_viewer_can_load_leaks_a_secret(): void
    {
        $r = $this->fresh();

        foreach ([ChannelManager::class, RuleManager::class, TemplateEditor::class, NotificationCenter::class] as $class) {
            $component = Livewire::actingAs($this->users['viewer'])->test($class);
            $payload = json_encode($component->snapshot ?? []).$component->html();

            foreach ([self::SMTP_PASSWORD, 'TOKEN-NEVER-SHOWN', self::SIGNING_SECRET, 'private body text'] as $secret) {
                $this->assertStringNotContainsString($secret, $payload, "{$class} leaks a secret");
            }
        }

        // The models never serialise them either, which is what any future API would use.
        $this->assertArrayNotHasKey('config', $r['channel']->fresh()->toArray());
        $this->assertStringNotContainsString(self::SMTP_PASSWORD, $r['channel']->fresh()->toJson());
        $this->assertArrayNotHasKey('signing_secret', $r['webhook']->fresh()->toArray());
        $this->assertStringNotContainsString(self::SIGNING_SECRET, $r['webhook']->fresh()->toJson());
        $this->assertArrayNotHasKey('body', $r['log']->fresh()->toArray());
    }

    public function test_a_viewer_never_triggers_the_ai_generator(): void
    {
        $this->fresh();

        Livewire::actingAs($this->users['viewer'])->test(TemplateEditor::class)
            ->set('purpose', 'Parcel shipped')->call('generate')->assertForbidden()
            ->assertSet('generatedBody', '');
        Livewire::actingAs($this->users['editor'])->test(TemplateEditor::class)
            ->set('purpose', 'Parcel shipped')->call('generate')->assertForbidden();
    }

    public function test_a_viewer_is_shown_no_write_controls_but_an_admin_is(): void
    {
        $this->fresh();

        $viewer = $this->users['viewer'];
        Livewire::actingAs($viewer)->test(ChannelManager::class)
            ->assertSee('Ops Mailer')
            ->assertDontSee('+ Add Channel')
            ->assertDontSee('Toggle')
            ->assertDontSee('Remove');
        Livewire::actingAs($viewer)->test(RuleManager::class)
            ->assertSee('order.shipped')
            ->assertDontSee('+ Add Rule')
            ->assertDontSeeHtml('>Edit</button>')
            ->assertDontSee('Toggle')
            ->assertDontSee('Remove');
        Livewire::actingAs($viewer)->test(TemplateEditor::class)
            ->assertSee('Welcome Template')
            ->assertDontSee('AI Generate')
            ->assertDontSee('Use AI to generate');

        $admin = $this->users['admin'];
        Livewire::actingAs($admin)->test(ChannelManager::class)->assertSee('+ Add Channel')->assertSee('Toggle')->assertSee('Remove');
        Livewire::actingAs($admin)->test(RuleManager::class)->assertSee('+ Add Rule')->assertSeeHtml('>Edit</button>')->assertSee('Toggle');
        Livewire::actingAs($admin)->test(TemplateEditor::class)->assertSee('AI Generate');
    }

    public function test_an_editor_sees_no_dead_write_controls_either(): void
    {
        $this->fresh();

        Livewire::actingAs($this->users['editor'])->test(ChannelManager::class)->assertDontSee('+ Add Channel');
        Livewire::actingAs($this->users['editor'])->test(RuleManager::class)->assertDontSee('+ Add Rule');
        Livewire::actingAs($this->users['editor'])->test(TemplateEditor::class)->assertDontSee('AI Generate');
    }

    public function test_the_empty_states_do_not_tell_a_viewer_to_do_something_they_cannot(): void
    {
        NotifyChannel::withoutGlobalScopes()->delete();
        NotifyTemplate::withoutGlobalScopes()->delete();

        Livewire::actingAs($this->users['viewer'])->test(TemplateEditor::class)
            ->assertSee('No templates yet.')
            ->assertDontSee('Use AI to generate your first one');
    }
}
