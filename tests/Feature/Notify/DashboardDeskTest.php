<?php

namespace Tests\Feature\Notify;

use App\Livewire\Notify\ChannelManager;
use App\Livewire\Notify\NotificationCenter;
use App\Livewire\Notify\RuleManager;
use App\Models\NotifyBatch;
use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\NotifyRule;
use App\Models\NotifyTemplate;
use App\Models\Team;
use App\Models\User;
use App\Support\DashboardAttention;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * R-51: the dashboard as a desk. "Needs attention" (two of its reads are new), the record, and the log
 * whose chosen row is shown on the slip.
 */
class DashboardDeskTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $owner;

    private User $viewer;

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

    private function channel(Team $team, array $extra = []): NotifyChannel
    {
        return NotifyChannel::create($extra + ['team_id' => $team->id, 'type' => 'email', 'name' => 'Primary Email', 'is_active' => true]);
    }

    private function rule(Team $team, NotifyChannel $channel, string $event, bool $active = true): NotifyRule
    {
        $template = NotifyTemplate::create(['team_id' => $team->id, 'name' => 'T '.$event, 'subject' => 's', 'body' => 'b', 'channel_type' => 'email']);

        return NotifyRule::create(['team_id' => $team->id, 'trigger_event' => $event, 'template_id' => $template->id, 'channel_id' => $channel->id, 'is_active' => $active]);
    }

    private function log(Team $team, string $status, array $extra = []): NotifyLog
    {
        return NotifyLog::create($extra + ['team_id' => $team->id, 'recipient' => $status.'@khanya.test', 'subject' => 'Subject '.$status, 'status' => $status, 'sent_at' => $status === 'sent' ? now() : null]);
    }

    private function sending(): NotifyChannel
    {
        $channel = $this->channel($this->team);
        $this->rule($this->team, $channel, 'task.assigned');

        return $channel;
    }

    public function test_a_healthy_company_is_told_nothing_needs_attention(): void
    {
        $channel = $this->sending();
        $this->log($this->team, 'sent', ['channel_id' => $channel->id]);

        $this->actingAs($this->owner)->get('/dashboard')->assertOk()
            ->assertSeeText('Nothing needs attention.')
            ->assertSeeText('1 message sent today, none failed.');
    }

    public function test_every_kind_of_trouble_gets_its_own_row_with_a_real_cause(): void
    {
        $email = $this->sending();
        $sms = $this->channel($this->team, ['type' => 'sms', 'name' => 'Dispatch SMS', 'test_status' => 'failed', 'last_tested_at' => now()->subHours(5)]);
        $slack = $this->channel($this->team, ['type' => 'slack', 'name' => 'Ops Slack', 'is_active' => false]);
        $this->rule($this->team, $slack, 'task.overdue');
        NotifyBatch::create(['team_id' => $this->team->id, 'name' => 'Fuel card reminder', 'status' => 'failed', 'total_recipients' => 20, 'sent_count' => 0]);
        $this->log($this->team, 'failed', ['channel_id' => $email->id]);
        $this->log($this->team, 'retrying', ['channel_id' => $email->id, 'next_retry_at' => now()->addMinutes(10)->subSeconds(20)]);

        $this->actingAs($this->owner)->get('/dashboard')->assertOk()
            ->assertDontSeeText('Nothing needs attention.')
            ->assertSeeText('1 message failed today')
            ->assertSeeText('Dispatch SMS failed its last test')
            ->assertSeeText('Tested 5 hours ago. Messages through it may not get out.')
            ->assertSeeText('task.overdue sends through Ops Slack, which is switched off')
            ->assertSeeText('Fuel card reminder failed')
            ->assertSeeText('0 of 20 sent')
            ->assertSeeText('1 message is being retried')
            ->assertSeeText('The next attempt is 9 minutes from now.')
            ->assertSeeText('5 things');
    }

    public function test_the_two_new_attention_reads_never_show_another_companys_records(): void
    {
        $this->sending();
        $theirOff = $this->channel($this->other, ['name' => 'Their Slack', 'is_active' => false]);
        $this->channel($this->other, ['name' => 'Their SMS', 'type' => 'sms', 'test_status' => 'failed']);
        $this->rule($this->other, $theirOff, 'their.event');
        NotifyBatch::create(['team_id' => $this->other->id, 'name' => 'Their batch', 'status' => 'failed', 'total_recipients' => 1, 'sent_count' => 0]);

        $this->actingAs($this->owner)->get('/dashboard')->assertOk()
            ->assertSeeText('Nothing needs attention.')
            ->assertDontSee('Their Slack')->assertDontSee('Their SMS')->assertDontSee('their.event')->assertDontSee('Their batch');
    }

    public function test_the_rule_and_channel_reads_do_not_grow_with_the_number_of_rules(): void
    {
        $email = $this->sending();
        $off = $this->channel($this->team, ['type' => 'slack', 'name' => 'Ops Slack', 'is_active' => false]);
        $this->rule($this->team, $off, 'a.one');

        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            DashboardAttention::items(0);

            return count(DB::getQueryLog());
        };
        $this->actingAs($this->owner);
        $one = $count();

        foreach (['a.two', 'a.three', 'a.four'] as $event) {
            $this->rule($this->team, $off, $event);
        }
        // Three rows are listed at most; the cost is the same however many rules route through an off channel.
        $this->assertSame($one, $count());
    }

    public function test_the_slip_defaults_to_the_newest_failed_message_and_shows_why(): void
    {
        $email = $this->sending();
        $rule = NotifyRule::first();
        $this->log($this->team, 'failed', ['recipient' => 'old-fail@khanya.test', 'channel_id' => $email->id, 'rule_id' => $rule->id, 'attempts' => 3, 'failure_reason' => 'The gateway rejected the number.', 'created_at' => now()->subHour(), 'body_preview' => 'SECRET-PREVIEW-TEXT']);
        $this->log($this->team, 'sent', ['recipient' => 'newest-sent@khanya.test', 'channel_id' => $email->id]);

        $html = (string) $this->actingAs($this->owner)->get('/dashboard')->assertOk()->getContent();
        $slip = substr($html, strpos($html, 'class="nf-slip"'));
        $slip = substr($slip, 0, strpos($slip, '</section>'));

        $this->assertStringContainsString('old-fail@khanya.test', $slip);
        $this->assertStringNotContainsString('newest-sent@khanya.test', $slip);
        $this->assertStringContainsString('The gateway rejected the number.', $slip);
        $this->assertStringContainsString('task.assigned', $slip);
        $this->assertStringContainsString('Why it failed', $slip);
        $this->assertStringNotContainsString('SECRET-PREVIEW-TEXT', $html, 'The message text is never shown.');
        $this->assertSame(1, substr_count($html, 'data-primary-action'));
    }

    public function test_choosing_a_row_shows_its_slip_and_survives_the_live_refresh(): void
    {
        $email = $this->sending();
        $this->log($this->team, 'failed', ['recipient' => 'fail@khanya.test', 'channel_id' => $email->id]);
        $sent = $this->log($this->team, 'sent', ['recipient' => 'picked@khanya.test', 'channel_id' => $email->id, 'created_at' => now()->subHour()]);

        Livewire::actingAs($this->owner)->test(NotificationCenter::class)
            ->assertSeeHtml('class="nf-slip"')->assertSee('fail@khanya.test')
            ->call('select', $sent->id)
            ->assertSet('selected', $sent->id)
            ->assertSeeInOrder(['Selected message', 'picked@khanya.test'])
            ->dispatch('notify.sent')
            ->assertSet('selected', $sent->id)
            ->assertSeeHtml('wire:key="slip-'.$sent->id.'"');
    }

    public function test_the_selection_param_is_checked_against_this_companys_rows(): void
    {
        $email = $this->sending();
        $mine = $this->log($this->team, 'sent', ['recipient' => 'mine@khanya.test', 'channel_id' => $email->id]);
        $theirs = $this->log($this->other, 'failed', ['recipient' => 'stranger@other.test']);

        $this->actingAs($this->owner)->get('/dashboard?log='.$theirs->id)->assertOk()
            ->assertDontSee('stranger@other.test')->assertSee('mine@khanya.test');
        $this->actingAs($this->owner)->get('/dashboard?log=999999')->assertOk()->assertSee('mine@khanya.test');
        $this->actingAs($this->owner)->get('/dashboard?log='.$mine->id)->assertOk()->assertSee('aria-current="true"', false);

        Livewire::actingAs($this->owner)->test(NotificationCenter::class)
            ->call('select', $theirs->id)->assertSet('selected', null)->assertDontSee('stranger@other.test');
    }

    public function test_a_filter_that_hides_the_chosen_row_falls_back_to_the_default(): void
    {
        $email = $this->sending();
        $sent = $this->log($this->team, 'sent', ['recipient' => 'sent@khanya.test', 'channel_id' => $email->id]);
        $this->log($this->team, 'failed', ['recipient' => 'failed@khanya.test', 'channel_id' => $email->id]);

        Livewire::actingAs($this->owner)->test(NotificationCenter::class)
            ->call('select', $sent->id)
            ->set('filterStatus', 'failed')
            ->assertSeeHtml('wire:key="slip-'.NotifyLog::where('status', 'failed')->value('id').'"');
    }

    public function test_the_slip_says_so_when_a_rule_or_channel_was_removed(): void
    {
        $this->sending();
        $this->log($this->team, 'sent', ['recipient' => 'orphan@khanya.test']);

        $this->actingAs($this->owner)->get('/dashboard')->assertOk()
            ->assertSeeText('No channel recorded')->assertSeeText('No rule recorded');
    }

    public function test_an_empty_log_keeps_the_frame_and_says_so(): void
    {
        $this->actingAs($this->owner)->get('/dashboard')->assertOk()
            ->assertSee('id="slip"', false)
            ->assertSeeText('Nothing has been handed over for '.$this->team->name.' yet.')
            ->assertSeeText('No notifications sent yet.');
    }

    public function test_only_a_manager_is_offered_edit_the_rule_on_the_slip(): void
    {
        $email = $this->sending();
        $this->log($this->team, 'sent', ['channel_id' => $email->id, 'rule_id' => NotifyRule::first()->id]);

        $this->actingAs($this->owner)->get('/dashboard')->assertSeeText('Edit the rule');
        $this->actingAs($this->viewer)->get('/dashboard')->assertOk()->assertDontSeeText('Edit the rule');
    }

    public function test_edit_the_rule_opens_the_form_for_a_manager_and_is_refused_to_a_viewer(): void
    {
        $this->sending();
        $rule = NotifyRule::first();

        Livewire::actingAs($this->owner)->test(RuleManager::class)->dispatch('notify.edit-rule', id: $rule->id)->assertSet('editingId', $rule->id);
        Livewire::actingAs($this->viewer)->test(RuleManager::class)->dispatch('notify.edit-rule', id: $rule->id)->assertForbidden();
    }

    public function test_remove_asks_first_and_keep_it_changes_nothing(): void
    {
        $channel = $this->channel($this->team);
        $rule = $this->rule($this->team, $channel, 'task.assigned');

        Livewire::actingAs($this->owner)->test(ChannelManager::class)
            ->call('confirmDelete', $channel->id)->assertSet('confirmingDeleteId', $channel->id)
            ->assertSeeText('Rules that use it will stop sending.')
            ->call('cancelDelete')->assertSet('confirmingDeleteId', null);
        $this->assertNotNull(NotifyChannel::find($channel->id));

        Livewire::actingAs($this->owner)->test(RuleManager::class)
            ->call('confirmDelete', $rule->id)->assertSeeText('That event stops being sent.')
            ->call('cancelDelete');
        $this->assertNotNull(NotifyRule::find($rule->id));

        Livewire::actingAs($this->owner)->test(ChannelManager::class)->call('confirmDelete', $channel->id)->call('deleteChannel', $channel->id)->assertSet('confirmingDeleteId', null);
        $this->assertNull(NotifyChannel::find($channel->id));
    }

    public function test_a_viewer_cannot_even_open_the_remove_question(): void
    {
        $channel = $this->channel($this->team);
        $rule = $this->rule($this->team, $channel, 'task.assigned');

        Livewire::actingAs($this->viewer)->test(ChannelManager::class)->call('confirmDelete', $channel->id)->assertForbidden();
        Livewire::actingAs($this->viewer)->test(RuleManager::class)->call('confirmDelete', $rule->id)->assertForbidden();
    }

    public function test_the_headline_and_the_record_follow_the_stage_and_the_old_intro_motion_is_gone(): void
    {
        // No channel: the headline is the finding sentence, the record shows only channels and rules, no attention section.
        $html = (string) $this->actingAs($this->owner)->get('/dashboard')->assertOk()
            ->assertSee('<h1 class="nf-title">', false)
            ->assertSeeText($this->team->name.' has no channel yet, so nothing can be sent.')
            ->assertDontSee('id="attention"', false)
            ->getContent();
        $this->assertSame(1, substr_count($html, 'data-primary-action'));
        $this->assertStringNotContainsString('nf-intro', $html);
        $this->assertStringNotContainsString('nf-rise', (string) file_get_contents(resource_path('css/notify.css')));
    }

    public function test_a_viewer_gets_the_standing_sentence_and_the_same_desk(): void
    {
        $email = $this->sending();
        $this->log($this->team, 'failed', ['channel_id' => $email->id]);

        $this->actingAs($this->viewer)->get('/dashboard')->assertOk()
            ->assertSeeText('You can read everything here.')
            ->assertSeeText('1 message failed today')->assertSeeText('Selected message');
    }
}
