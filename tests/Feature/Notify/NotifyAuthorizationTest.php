<?php

namespace Tests\Feature\Notify;

use App\Livewire\Notify\ChannelManager;
use App\Livewire\Notify\RuleManager;
use App\Livewire\Notify\TemplateEditor;
use App\Models\NotifyChannel;
use App\Models\NotifyRule;
use App\Models\NotifySchedule;
use App\Models\NotifyTemplate;
use App\Models\NotifyWebhook;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class NotifyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private User $editor;

    private User $outsider;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withPersonalTeam()->create();
        $this->team = $this->owner->currentTeam;
        $this->admin = $this->member('admin');
        $this->editor = $this->member('editor');
        $this->outsider = User::factory()->withPersonalTeam()->create();
    }

    private function member(string $role): User
    {
        $user = User::factory()->create();
        $this->team->users()->attach($user, ['role' => $role]);
        $user->switchTeam($this->team);

        return $user->fresh();
    }

    /** @return array<string, object> */
    private function resources(): array
    {
        $channel = NotifyChannel::create(['team_id' => $this->team->id, 'type' => 'email', 'name' => 'Email']);
        $template = NotifyTemplate::create(['team_id' => $this->team->id, 'name' => 'T', 'body' => 'b']);
        $rule = NotifyRule::create(['team_id' => $this->team->id, 'template_id' => $template->id, 'channel_id' => $channel->id, 'trigger_event' => 'x.y']);

        return [
            'channel' => $channel,
            'template' => $template,
            'rule' => $rule,
            'webhook' => NotifyWebhook::create(['team_id' => $this->team->id, 'name' => 'W']),
            'schedule' => NotifySchedule::create(['team_id' => $this->team->id, 'rule_id' => $rule->id, 'name' => 'S', 'cron_expression' => '0 9 * * *']),
        ];
    }

    public function test_owner_and_admin_can_manage_every_notify_resource(): void
    {
        foreach ([$this->owner, $this->admin] as $user) {
            foreach ($this->resources() as $name => $model) {
                $this->assertTrue(Gate::forUser($user)->allows('view', $model), "{$name} view");
                $this->assertTrue(Gate::forUser($user)->allows('update', $model), "{$name} update");
                $this->assertTrue(Gate::forUser($user)->allows('delete', $model), "{$name} delete");
                $this->assertTrue(Gate::forUser($user)->allows('create', [$model::class, $this->team]), "{$name} create");
            }
        }
    }

    public function test_editors_are_read_only_on_every_notify_resource(): void
    {
        foreach ($this->resources() as $name => $model) {
            $this->assertTrue(Gate::forUser($this->editor)->allows('view', $model), "{$name} view");
            $this->assertFalse(Gate::forUser($this->editor)->allows('update', $model), "{$name} update");
            $this->assertFalse(Gate::forUser($this->editor)->allows('delete', $model), "{$name} delete");
            $this->assertFalse(Gate::forUser($this->editor)->allows('create', [$model::class, $this->team]), "{$name} create");
        }
    }

    public function test_people_outside_the_team_can_do_nothing(): void
    {
        foreach ($this->resources() as $name => $model) {
            foreach (['view', 'update', 'delete'] as $ability) {
                $this->assertFalse(Gate::forUser($this->outsider)->allows($ability, $model), "{$name} {$ability}");
            }
            $this->assertFalse(Gate::forUser($this->outsider)->allows('create', [$model::class, $this->team]), "{$name} create");
        }
    }

    public function test_an_editor_cannot_change_channels_through_the_ui(): void
    {
        $channel = NotifyChannel::create(['team_id' => $this->team->id, 'type' => 'email', 'name' => 'Email']);

        Livewire::actingAs($this->editor)->test(ChannelManager::class)
            ->set('name', 'Sneaky')->call('addChannel')->assertForbidden();
        Livewire::actingAs($this->editor)->test(ChannelManager::class)->call('toggleChannel', $channel->id)->assertForbidden();
        Livewire::actingAs($this->editor)->test(ChannelManager::class)->call('deleteChannel', $channel->id)->assertForbidden();

        $this->assertTrue($channel->fresh()->is_active);
        $this->assertSame(1, NotifyChannel::withoutGlobalScopes()->count());
    }

    public function test_an_admin_can_still_manage_channels_through_the_ui(): void
    {
        Livewire::actingAs($this->admin)->test(ChannelManager::class)
            ->set('name', 'Ops')->call('addChannel')->assertHasNoErrors();

        $this->assertSame(1, NotifyChannel::withoutGlobalScopes()->where('name', 'Ops')->count());
    }

    public function test_an_editor_cannot_change_rules_or_templates_through_the_ui(): void
    {
        ['channel' => $channel, 'template' => $template, 'rule' => $rule] = $this->resources();

        Livewire::actingAs($this->editor)->test(RuleManager::class)
            ->set('triggerEvent', 'a.b')->set('templateId', (string) $template->id)->set('channelId', (string) $channel->id)
            ->call('saveRule')->assertForbidden();
        Livewire::actingAs($this->editor)->test(RuleManager::class)->call('toggleRule', $rule->id)->assertForbidden();
        Livewire::actingAs($this->editor)->test(RuleManager::class)->call('deleteRule', $rule->id)->assertForbidden();
        Livewire::actingAs($this->editor)->test(TemplateEditor::class)
            ->set('templateName', 'N')->set('generatedBody', 'Body')->call('saveTemplate')->assertForbidden();

        $this->assertSame(1, NotifyRule::withoutGlobalScopes()->count());
        $this->assertTrue($rule->fresh()->is_active);
        $this->assertSame(1, NotifyTemplate::withoutGlobalScopes()->count());
    }

    public function test_a_rule_cannot_reference_another_teams_channel_or_template(): void
    {
        $other = $this->outsider->currentTeam;
        $foreignChannel = NotifyChannel::create(['team_id' => $other->id, 'type' => 'email', 'name' => 'Theirs']);
        $foreignTemplate = NotifyTemplate::create(['team_id' => $other->id, 'name' => 'Theirs', 'body' => 'b']);
        $ownChannel = NotifyChannel::create(['team_id' => $this->team->id, 'type' => 'email', 'name' => 'Mine']);
        $ownTemplate = NotifyTemplate::create(['team_id' => $this->team->id, 'name' => 'Mine', 'body' => 'b']);

        foreach ([
            ['channel_id' => $foreignChannel->id, 'template_id' => $ownTemplate->id],
            ['channel_id' => $ownChannel->id, 'template_id' => $foreignTemplate->id],
        ] as $ids) {
            try {
                NotifyRule::create(['team_id' => $this->team->id, 'trigger_event' => 'x.y'] + $ids);
                $this->fail('Cross-team rule was saved.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        $rule = NotifyRule::create(['team_id' => $this->team->id, 'trigger_event' => 'x.y', 'channel_id' => $ownChannel->id, 'template_id' => $ownTemplate->id]);
        $this->expectException(ValidationException::class);
        $rule->update(['channel_id' => $foreignChannel->id]);
    }

    public function test_a_rule_may_still_have_no_channel_or_template_yet(): void
    {
        $rule = NotifyRule::create(['team_id' => $this->team->id, 'trigger_event' => 'x.y']);

        $this->assertNotNull($rule->id);
    }
}
