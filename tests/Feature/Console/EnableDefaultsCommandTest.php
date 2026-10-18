<?php

namespace Tests\Feature\Console;

use App\Models\NotifyChannel;
use App\Models\NotifyRule;
use App\Models\NotifyTemplate;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnableDefaultsCommandTest extends TestCase
{
    use RefreshDatabase;

    private const UUID = '44444444-4444-4444-8444-444444444444';

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = User::factory()->withPersonalTeam()->create()->currentTeam;
        DB::table('teams')->where('id', $this->team->id)->update(['infodot_team_uuid' => self::UUID]);
        NotifyChannel::create(['team_id' => $this->team->id, 'type' => 'email', 'name' => 'Primary Email']);
    }

    /** @return array<int, string> */
    private function enabledEvents(): array
    {
        return NotifyRule::withoutGlobalScopes()->where('team_id', $this->team->id)->where('is_active', true)->orderBy('trigger_event')->pluck('trigger_event')->all();
    }

    public function test_it_turns_on_every_event_of_one_platform(): void
    {
        $this->artisan('notify:enable-defaults', ['team_uuid' => self::UUID, 'platform' => 'tasks'])
            ->expectsOutputToContain('task.assigned')
            ->assertSuccessful();

        $this->assertSame(['task.assigned', 'task.due_soon', 'task.escalated', 'task.mentioned'], $this->enabledEvents());
    }

    public function test_without_a_platform_it_turns_on_the_whole_catalogue(): void
    {
        $this->artisan('notify:enable-defaults', ['team_uuid' => self::UUID])->assertSuccessful();

        $this->assertCount(16, $this->enabledEvents());
    }

    public function test_events_option_limits_it_to_the_named_events(): void
    {
        $this->artisan('notify:enable-defaults', ['team_uuid' => self::UUID, 'platform' => 'tasks', '--events' => 'task.assigned, task.mentioned'])->assertSuccessful();

        $this->assertSame(['task.assigned', 'task.mentioned'], $this->enabledEvents());
    }

    public function test_events_option_works_without_a_platform(): void
    {
        $this->artisan('notify:enable-defaults', ['team_uuid' => self::UUID, '--events' => 'leave.decided,ride.expired'])->assertSuccessful();

        $this->assertSame(['leave.decided', 'ride.expired'], $this->enabledEvents());
    }

    public function test_running_it_twice_changes_nothing_and_keeps_edited_templates(): void
    {
        $this->artisan('notify:enable-defaults', ['team_uuid' => self::UUID, 'platform' => 'farms'])->assertSuccessful();
        NotifyTemplate::withoutGlobalScopes()->where('team_id', $this->team->id)->update(['body' => 'Our harvest wording']);

        $this->artisan('notify:enable-defaults', ['team_uuid' => self::UUID, 'platform' => 'farms'])->assertSuccessful();

        $this->assertSame(1, NotifyRule::withoutGlobalScopes()->where('team_id', $this->team->id)->count());
        $this->assertSame('Our harvest wording', NotifyTemplate::withoutGlobalScopes()->where('team_id', $this->team->id)->sole()->body);
    }

    public function test_an_unknown_company_fails_and_creates_nothing(): void
    {
        $this->artisan('notify:enable-defaults', ['team_uuid' => '99999999-9999-4999-8999-999999999999'])
            ->expectsOutputToContain('No company')
            ->assertFailed();

        $this->assertSame(0, NotifyRule::withoutGlobalScopes()->count());
    }

    public function test_an_unknown_platform_or_event_fails_before_anything_is_created(): void
    {
        $this->artisan('notify:enable-defaults', ['team_uuid' => self::UUID, 'platform' => 'nonsense'])->expectsOutputToContain('Unknown platform')->assertFailed();
        $this->artisan('notify:enable-defaults', ['team_uuid' => self::UUID, 'platform' => 'tasks', '--events' => 'task.assigned,task.unheard_of'])->expectsOutputToContain('task.unheard_of')->assertFailed();
        $this->artisan('notify:enable-defaults', ['team_uuid' => self::UUID, 'platform' => 'tasks', '--events' => 'leave.decided'])->expectsOutputToContain('leave.decided')->assertFailed();

        $this->assertSame(0, NotifyRule::withoutGlobalScopes()->count());
    }

    public function test_it_says_so_when_the_company_has_no_email_channel_yet(): void
    {
        NotifyChannel::withoutGlobalScopes()->delete();

        $this->artisan('notify:enable-defaults', ['team_uuid' => self::UUID, 'platform' => 'tasks', '--events' => 'task.assigned'])
            ->expectsOutputToContain('no email channel yet')
            ->assertSuccessful();

        $this->assertNull(NotifyRule::withoutGlobalScopes()->sole()->channel_id);
    }
}
