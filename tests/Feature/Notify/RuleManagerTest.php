<?php

namespace Tests\Feature\Notify;

use App\Livewire\Notify\RuleManager;
use App\Models\NotifyChannel;
use App\Models\NotifyRule;
use App\Models\NotifyTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * NotifyRule management UI (README/wiki.md §6: the model/relationships
 * existed since the first migration, no UI ever let a team create one
 * outside tinker). Follows the same abort-403-on-no-team convention as
 * ChannelManager/TemplateEditor.
 */
class RuleManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_rule_succeeds_with_an_active_team(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $template = NotifyTemplate::create([
            'team_id' => $user->currentTeam->id,
            'name' => 'Signup Welcome',
            'subject' => 'Welcome',
            'body' => 'Hi {{ name }}',
            'channel_type' => 'email',
        ]);
        $channel = NotifyChannel::create([
            'team_id' => $user->currentTeam->id,
            'type' => 'email',
            'name' => 'Primary Email',
        ]);

        Livewire::actingAs($user)
            ->test(RuleManager::class)
            ->set('triggerEvent', 'user.signup')
            ->set('templateId', (string) $template->id)
            ->set('channelId', (string) $channel->id)
            ->call('saveRule')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notify_rules', [
            'team_id' => $user->currentTeam->id,
            'trigger_event' => 'user.signup',
            'template_id' => $template->id,
            'channel_id' => $channel->id,
            'is_active' => true,
        ]);
    }

    public function test_add_rule_accepts_valid_json_conditions(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $template = NotifyTemplate::create([
            'team_id' => $user->currentTeam->id,
            'name' => 'Payment Failed',
            'subject' => 'Payment failed',
            'body' => 'Your payment failed',
            'channel_type' => 'email',
        ]);
        $channel = NotifyChannel::create([
            'team_id' => $user->currentTeam->id,
            'type' => 'email',
            'name' => 'Primary Email',
        ]);

        Livewire::actingAs($user)
            ->test(RuleManager::class)
            ->set('triggerEvent', 'payment.failed')
            ->set('conditionsJson', '{"amount_gt": 100}')
            ->set('templateId', (string) $template->id)
            ->set('channelId', (string) $channel->id)
            ->call('saveRule')
            ->assertHasNoErrors();

        $rule = NotifyRule::where('trigger_event', 'payment.failed')->firstOrFail();
        $this->assertSame(['amount_gt' => 100], $rule->conditions);
    }

    public function test_add_rule_rejects_malformed_json_conditions(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $template = NotifyTemplate::create([
            'team_id' => $user->currentTeam->id, 'name' => 'T', 'subject' => 'S', 'body' => 'B', 'channel_type' => 'email',
        ]);
        $channel = NotifyChannel::create([
            'team_id' => $user->currentTeam->id, 'type' => 'email', 'name' => 'C',
        ]);

        Livewire::actingAs($user)
            ->test(RuleManager::class)
            ->set('triggerEvent', 'user.signup')
            ->set('conditionsJson', '{not valid json')
            ->set('templateId', (string) $template->id)
            ->set('channelId', (string) $channel->id)
            ->call('saveRule')
            ->assertHasErrors(['conditionsJson']);
    }

    public function test_edit_populates_the_form_and_updates_the_rule(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $template = NotifyTemplate::create([
            'team_id' => $user->currentTeam->id, 'name' => 'T', 'subject' => 'S', 'body' => 'B', 'channel_type' => 'email',
        ]);
        $channel = NotifyChannel::create([
            'team_id' => $user->currentTeam->id, 'type' => 'email', 'name' => 'C',
        ]);
        $rule = NotifyRule::create([
            'team_id' => $user->currentTeam->id,
            'trigger_event' => 'user.signup',
            'template_id' => $template->id,
            'channel_id' => $channel->id,
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(RuleManager::class)
            ->call('edit', $rule->id)
            ->assertSet('triggerEvent', 'user.signup')
            ->set('triggerEvent', 'user.signup.v2')
            ->call('saveRule')
            ->assertHasNoErrors();

        $this->assertSame('user.signup.v2', $rule->fresh()->trigger_event);
        $this->assertDatabaseCount('notify_rules', 1);
    }

    public function test_toggle_rule_flips_active_state(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $template = NotifyTemplate::create([
            'team_id' => $user->currentTeam->id, 'name' => 'T', 'subject' => 'S', 'body' => 'B', 'channel_type' => 'email',
        ]);
        $channel = NotifyChannel::create([
            'team_id' => $user->currentTeam->id, 'type' => 'email', 'name' => 'C',
        ]);
        $rule = NotifyRule::create([
            'team_id' => $user->currentTeam->id,
            'trigger_event' => 'user.signup',
            'template_id' => $template->id,
            'channel_id' => $channel->id,
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(RuleManager::class)
            ->call('toggleRule', $rule->id);

        $this->assertFalse($rule->fresh()->is_active);
    }

    public function test_delete_rule_removes_it(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $template = NotifyTemplate::create([
            'team_id' => $user->currentTeam->id, 'name' => 'T', 'subject' => 'S', 'body' => 'B', 'channel_type' => 'email',
        ]);
        $channel = NotifyChannel::create([
            'team_id' => $user->currentTeam->id, 'type' => 'email', 'name' => 'C',
        ]);
        $rule = NotifyRule::create([
            'team_id' => $user->currentTeam->id,
            'trigger_event' => 'user.signup',
            'template_id' => $template->id,
            'channel_id' => $channel->id,
        ]);

        Livewire::actingAs($user)
            ->test(RuleManager::class)
            ->call('deleteRule', $rule->id);

        $this->assertDatabaseMissing('notify_rules', ['id' => $rule->id]);
    }

    public function test_save_rule_aborts_when_user_has_no_active_team(): void
    {
        $user = User::factory()->create(['current_team_id' => null]);

        Livewire::actingAs($user)
            ->test(RuleManager::class)
            ->set('triggerEvent', 'user.signup')
            ->set('templateId', '1')
            ->set('channelId', '1')
            ->call('saveRule')
            ->assertStatus(403);
    }

    public function test_add_rule_rejects_conditions_that_read_a_recipient_activity_field(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $template = NotifyTemplate::create([
            'team_id' => $user->currentTeam->id, 'name' => 'T', 'subject' => 'S', 'body' => 'B', 'channel_type' => 'email',
        ]);
        $channel = NotifyChannel::create([
            'team_id' => $user->currentTeam->id, 'type' => 'email', 'name' => 'C',
        ]);

        // Governance rule (wiki.md §5): no notification class may trigger
        // on recipient absence -- previously documented only, never
        // enforced (wiki.md §6 Roadmap/Open Questions).
        Livewire::actingAs($user)
            ->test(RuleManager::class)
            ->set('triggerEvent', 'user.signup')
            ->set('conditionsJson', '{"days_since_last_login": 30}')
            ->set('templateId', (string) $template->id)
            ->set('channelId', (string) $channel->id)
            ->call('saveRule')
            ->assertHasErrors(['conditionsJson']);

        $this->assertDatabaseMissing('notify_rules', ['trigger_event' => 'user.signup']);
    }

    public function test_add_rule_accepts_legitimate_domain_event_conditions(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $template = NotifyTemplate::create([
            'team_id' => $user->currentTeam->id, 'name' => 'T', 'subject' => 'S', 'body' => 'B', 'channel_type' => 'email',
        ]);
        $channel = NotifyChannel::create([
            'team_id' => $user->currentTeam->id, 'type' => 'email', 'name' => 'C',
        ]);

        Livewire::actingAs($user)
            ->test(RuleManager::class)
            ->set('triggerEvent', 'payment.failed')
            ->set('conditionsJson', '{"amount_gt": 100}')
            ->set('templateId', (string) $template->id)
            ->set('channelId', (string) $channel->id)
            ->call('saveRule')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notify_rules', ['trigger_event' => 'payment.failed']);
    }

    public function test_add_rule_accepts_an_optional_rate_ceiling(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $template = NotifyTemplate::create([
            'team_id' => $user->currentTeam->id, 'name' => 'T', 'subject' => 'S', 'body' => 'B', 'channel_type' => 'email',
        ]);
        $channel = NotifyChannel::create([
            'team_id' => $user->currentTeam->id, 'type' => 'email', 'name' => 'C',
        ]);

        Livewire::actingAs($user)
            ->test(RuleManager::class)
            ->set('triggerEvent', 'alert.critical')
            ->set('rateCeilingPerHour', '50')
            ->set('templateId', (string) $template->id)
            ->set('channelId', (string) $channel->id)
            ->call('saveRule')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notify_rules', [
            'trigger_event' => 'alert.critical',
            'rate_ceiling_per_hour' => 50,
        ]);
    }

    public function test_add_rule_rejects_a_non_positive_rate_ceiling(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $template = NotifyTemplate::create([
            'team_id' => $user->currentTeam->id, 'name' => 'T', 'subject' => 'S', 'body' => 'B', 'channel_type' => 'email',
        ]);
        $channel = NotifyChannel::create([
            'team_id' => $user->currentTeam->id, 'type' => 'email', 'name' => 'C',
        ]);

        Livewire::actingAs($user)
            ->test(RuleManager::class)
            ->set('triggerEvent', 'alert.critical')
            ->set('rateCeilingPerHour', '0')
            ->set('templateId', (string) $template->id)
            ->set('channelId', (string) $channel->id)
            ->call('saveRule')
            ->assertHasErrors(['rateCeilingPerHour']);
    }

    public function test_a_rule_cannot_reference_another_teams_template_or_channel(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $otherTeamUser = User::factory()->withPersonalTeam()->create();

        $foreignTemplate = NotifyTemplate::create([
            'team_id' => $otherTeamUser->currentTeam->id, 'name' => 'T', 'subject' => 'S', 'body' => 'B', 'channel_type' => 'email',
        ]);
        $channel = NotifyChannel::create([
            'team_id' => $user->currentTeam->id, 'type' => 'email', 'name' => 'C',
        ]);

        // Rule::exists() queries the notify_templates table directly and
        // does not go through NotifyTemplate's HasTeamScope global scope
        // (that's an Eloquent-query-builder feature, exists: isn't one) --
        // a bare "exists:notify_templates,id" would let a hand-crafted
        // request reference another team's template. The component scopes
        // the exists check to the acting team explicitly; this proves a
        // foreign-team template id is rejected, not silently accepted.
        Livewire::actingAs($user)
            ->test(RuleManager::class)
            ->set('triggerEvent', 'user.signup')
            ->set('templateId', (string) $foreignTemplate->id)
            ->set('channelId', (string) $channel->id)
            ->call('saveRule')
            ->assertHasErrors(['templateId']);

        $this->assertDatabaseMissing('notify_rules', ['trigger_event' => 'user.signup']);
    }
}
