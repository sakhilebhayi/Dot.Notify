<?php

namespace Tests\Feature\Notify;

use App\Models\NotifyChannel;
use App\Models\NotifyRule;
use App\Models\NotifyTemplate;
use App\Models\Team;
use App\Models\User;
use App\Services\Notify\EventEnabler;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Switching a catalogued event on or off for one company: it creates (once) the
 * company's own template and rule from the default, and never deletes or
 * overwrites anything the company edited.
 */
class EventEnablerTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private Team $other;

    private EventEnabler $enabler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = User::factory()->withPersonalTeam()->create()->currentTeam;
        $this->other = User::factory()->withPersonalTeam()->create()->currentTeam;
        $this->enabler = app(EventEnabler::class);
    }

    private function channel(Team $team, string $type = 'email', string $name = 'Primary Email', bool $active = true): NotifyChannel
    {
        return NotifyChannel::create(['team_id' => $team->id, 'type' => $type, 'name' => $name, 'is_active' => $active]);
    }

    /** @return Collection<int, NotifyRule> */
    private function rules(Team $team, string $event = 'task.assigned')
    {
        return NotifyRule::withoutGlobalScopes()->where('team_id', $team->id)->where('trigger_event', $event)->orderBy('id')->get();
    }

    public function test_enabling_creates_the_template_and_an_active_rule_on_the_first_email_channel(): void
    {
        $channel = $this->channel($this->team);

        $state = $this->enabler->enable($this->team, 'tasks', 'task.assigned');

        $this->assertSame('on', $state);
        $rule = $this->rules($this->team)->sole();
        $this->assertTrue($rule->is_active);
        $this->assertSame($channel->id, $rule->channel_id);

        $template = NotifyTemplate::withoutGlobalScopes()->findOrFail($rule->template_id);
        $this->assertSame($this->team->id, $template->team_id);
        $this->assertSame('New task: {{ task_title }}', $template->subject);
        $this->assertStringContainsString('{{ task_title }}', $template->body);
        $this->assertSame('email', $template->channel_type);
        $this->assertSame('catalogue.task.assigned', $template->slug);
        $this->assertSame('Dot.Tasks: Task assigned', $template->name);
        $this->assertEqualsCanonicalizing(['task_title', 'board_name', 'assigned_by', 'due_date', 'url'], $template->variables);
    }

    public function test_the_channel_is_the_first_active_email_channel_never_another_type_inactive_or_another_team(): void
    {
        $this->channel($this->other, 'email', 'Not ours');
        $this->channel($this->team, 'slack', 'Ops Slack');
        $this->channel($this->team, 'email', 'Switched off', active: false);
        $first = $this->channel($this->team, 'email', 'Second Email');
        $this->channel($this->team, 'email', 'Third Email');

        $this->enabler->enable($this->team, 'tasks', 'task.assigned');

        $this->assertSame($first->id, $this->rules($this->team)->sole()->channel_id);
    }

    public function test_enabling_twice_changes_nothing(): void
    {
        $this->channel($this->team);

        $this->enabler->enable($this->team, 'tasks', 'task.assigned');
        $this->enabler->enable($this->team, 'tasks', 'task.assigned');

        $this->assertCount(1, $this->rules($this->team));
        $this->assertSame(1, NotifyTemplate::withoutGlobalScopes()->where('team_id', $this->team->id)->count());
    }

    public function test_one_event_never_touches_another_event_or_another_team(): void
    {
        $this->channel($this->team);
        $this->channel($this->other);

        $this->enabler->enable($this->team, 'tasks', 'task.assigned');

        $this->assertCount(0, $this->rules($this->team, 'task.due_soon'));
        $this->assertSame(0, NotifyRule::withoutGlobalScopes()->where('team_id', $this->other->id)->count());
        $this->assertSame(0, NotifyTemplate::withoutGlobalScopes()->where('team_id', $this->other->id)->count());
    }

    public function test_with_no_email_channel_the_event_is_flagged_not_silently_on(): void
    {
        $this->channel($this->team, 'slack', 'Ops Slack');

        $state = $this->enabler->enable($this->team, 'tasks', 'task.assigned');

        $this->assertSame('no_channel', $state);
        $this->assertSame('no_channel', $this->enabler->state($this->team, 'task.assigned'));
        $rule = $this->rules($this->team)->sole();
        $this->assertTrue($rule->is_active);
        $this->assertNull($rule->channel_id);
    }

    public function test_enabling_again_once_an_email_channel_exists_attaches_it(): void
    {
        $this->enabler->enable($this->team, 'tasks', 'task.assigned');
        $channel = $this->channel($this->team);

        $state = $this->enabler->enable($this->team, 'tasks', 'task.assigned');

        $this->assertSame('on', $state);
        $this->assertCount(1, $this->rules($this->team));
        $this->assertSame($channel->id, $this->rules($this->team)->sole()->channel_id);
    }

    public function test_disabling_deactivates_the_rule_and_deletes_nothing(): void
    {
        $this->channel($this->team);
        $this->enabler->enable($this->team, 'tasks', 'task.assigned');

        $count = $this->enabler->disable($this->team, 'task.assigned');

        $this->assertSame(1, $count);
        $this->assertSame('off', $this->enabler->state($this->team, 'task.assigned'));
        $this->assertFalse($this->rules($this->team)->sole()->is_active);
        $this->assertSame(1, NotifyTemplate::withoutGlobalScopes()->where('team_id', $this->team->id)->count());
    }

    public function test_disabling_an_event_that_was_never_on_is_a_harmless_no_op(): void
    {
        $this->assertSame(0, $this->enabler->disable($this->team, 'task.assigned'));
        $this->assertSame('off', $this->enabler->state($this->team, 'task.assigned'));
    }

    public function test_enabling_again_reactivates_the_same_rule_and_never_overwrites_an_edited_template(): void
    {
        $this->channel($this->team);
        $this->enabler->enable($this->team, 'tasks', 'task.assigned');
        $rule = $this->rules($this->team)->sole();
        NotifyTemplate::withoutGlobalScopes()->whereKey($rule->template_id)->update(['subject' => 'Our own subject {{ task_title }}', 'body' => 'Our own wording: {{ task_title }}']);
        $this->enabler->disable($this->team, 'task.assigned');

        $state = $this->enabler->enable($this->team, 'tasks', 'task.assigned');

        $this->assertSame('on', $state);
        $this->assertSame($rule->id, $this->rules($this->team)->sole()->id);
        $template = NotifyTemplate::withoutGlobalScopes()->findOrFail($rule->template_id);
        $this->assertSame('Our own subject {{ task_title }}', $template->subject);
        $this->assertSame('Our own wording: {{ task_title }}', $template->body);
        $this->assertSame(1, NotifyTemplate::withoutGlobalScopes()->where('team_id', $this->team->id)->count());
    }

    public function test_an_edited_template_is_reused_not_overwritten_when_the_rule_itself_was_deleted(): void
    {
        $this->channel($this->team);
        $this->enabler->enable($this->team, 'tasks', 'task.assigned');
        $template = NotifyTemplate::withoutGlobalScopes()->where('team_id', $this->team->id)->sole();
        $template->update(['body' => 'Our own wording: {{ task_title }}', 'is_active' => false]);
        NotifyRule::withoutGlobalScopes()->where('team_id', $this->team->id)->delete();

        $this->enabler->enable($this->team, 'tasks', 'task.assigned');

        $this->assertSame(1, NotifyTemplate::withoutGlobalScopes()->where('team_id', $this->team->id)->count());
        $template->refresh();
        $this->assertSame('Our own wording: {{ task_title }}', $template->body);
        $this->assertTrue($template->is_active, 'Switching the event on switches its template back on.');
        $this->assertSame($template->id, $this->rules($this->team)->sole()->template_id);
    }

    public function test_a_rule_the_company_wrote_by_hand_is_reused_not_duplicated_or_rewired(): void
    {
        $slack = $this->channel($this->team, 'slack', 'Ops Slack');
        $this->channel($this->team);
        $template = NotifyTemplate::create(['team_id' => $this->team->id, 'name' => 'Mine', 'body' => 'custom']);
        $rule = NotifyRule::create(['team_id' => $this->team->id, 'template_id' => $template->id, 'channel_id' => $slack->id, 'trigger_event' => 'task.assigned', 'is_active' => false, 'rate_ceiling_per_hour' => 30]);

        $state = $this->enabler->enable($this->team, 'tasks', 'task.assigned');

        $this->assertSame('on', $state);
        $rule->refresh();
        $this->assertTrue($rule->is_active);
        $this->assertSame($slack->id, $rule->channel_id, 'A channel the company chose is kept.');
        $this->assertSame($template->id, $rule->template_id);
        $this->assertSame(30, $rule->rate_ceiling_per_hour);
        $this->assertCount(1, $this->rules($this->team));
        $this->assertSame(1, NotifyTemplate::withoutGlobalScopes()->where('team_id', $this->team->id)->count());
    }

    public function test_disabling_turns_off_every_active_rule_for_the_event_including_hand_written_ones(): void
    {
        $channel = $this->channel($this->team);
        $template = NotifyTemplate::create(['team_id' => $this->team->id, 'name' => 'Mine', 'body' => 'custom']);
        foreach (range(1, 2) as $i) {
            NotifyRule::create(['team_id' => $this->team->id, 'template_id' => $template->id, 'channel_id' => $channel->id, 'trigger_event' => 'task.assigned']);
        }

        $this->assertSame(2, $this->enabler->disable($this->team, 'task.assigned'));

        $this->assertSame(0, $this->rules($this->team)->where('is_active', true)->count());
        $this->assertCount(2, $this->rules($this->team));
    }

    public function test_a_deleted_template_is_recreated_for_a_rule_that_lost_it(): void
    {
        $this->channel($this->team);
        $this->enabler->enable($this->team, 'tasks', 'task.assigned');
        NotifyTemplate::withoutGlobalScopes()->where('team_id', $this->team->id)->delete();
        $this->assertNull($this->rules($this->team)->sole()->template_id);

        $this->enabler->enable($this->team, 'tasks', 'task.assigned');

        $this->assertNotNull($this->rules($this->team)->sole()->template_id);
        $this->assertSame(1, NotifyTemplate::withoutGlobalScopes()->where('team_id', $this->team->id)->count());
    }

    public function test_the_state_is_blocked_when_the_channel_or_template_is_switched_off(): void
    {
        $channel = $this->channel($this->team);
        $this->enabler->enable($this->team, 'tasks', 'task.assigned');

        $channel->update(['is_active' => false]);
        $this->assertSame('blocked', $this->enabler->state($this->team, 'task.assigned'));

        $channel->update(['is_active' => true]);
        $this->assertSame('on', $this->enabler->state($this->team, 'task.assigned'));

        NotifyTemplate::withoutGlobalScopes()->where('team_id', $this->team->id)->update(['is_active' => false]);
        $this->assertSame('blocked', $this->enabler->state($this->team, 'task.assigned'));
    }

    public function test_states_reports_every_catalogued_event_for_the_company(): void
    {
        $this->channel($this->team);
        $this->enabler->enable($this->team, 'tasks', 'task.assigned');

        $states = $this->enabler->states($this->team);

        $this->assertCount(16, $states);
        $this->assertSame('on', $states['task.assigned']);
        $this->assertSame('off', $states['task.due_soon']);
        $this->assertSame('off', $states['invoice.overdue']);
    }

    public function test_enabling_a_whole_platform_turns_on_each_of_its_events_once(): void
    {
        $this->channel($this->team);

        $result = $this->enabler->enablePlatform($this->team, 'tasks');

        $this->assertSame(['task.assigned' => 'on', 'task.due_soon' => 'on', 'task.escalated' => 'on', 'task.mentioned' => 'on'], $result);
        $this->assertSame(0, NotifyRule::withoutGlobalScopes()->where('team_id', $this->team->id)->where('trigger_event', 'like', 'leave.%')->count());
        $this->enabler->enablePlatform($this->team, 'tasks');
        $this->assertSame(4, NotifyRule::withoutGlobalScopes()->where('team_id', $this->team->id)->count());
    }

    public function test_an_event_the_catalogue_does_not_know_cannot_be_enabled(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->enabler->enable($this->team, 'tasks', 'task.unheard_of');
    }

    public function test_an_event_cannot_be_enabled_under_a_platform_that_does_not_own_it(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->enabler->enable($this->team, 'billing', 'task.assigned');
    }
}
