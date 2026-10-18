<?php

namespace Tests\Feature\Notify;

use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\NotifyRule;
use App\Models\NotifySchedule;
use App\Models\NotifyTemplate;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * notify:dispatch-schedules — the execution half of the notify_schedules
 * gap (README/wiki.md §6: the table existed since the first migration,
 * nothing read or wrote it). 2026-08-10 is a real Monday; 0 9 * * 1 in UTC
 * matches 2026-08-10 09:00:00 UTC exactly, so tests freeze time there
 * rather than depending on the wall clock's actual minute.
 */
class DispatchDueSchedulesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeRuleAndSchedule(Team $team, array $scheduleOverrides = [], array $ruleOverrides = []): NotifySchedule
    {
        $template = NotifyTemplate::create([
            'team_id' => $team->id, 'name' => 'Weekly Digest', 'subject' => 'Your weekly digest',
            'body' => 'Here is your digest.', 'channel_type' => 'email',
        ]);
        $channel = NotifyChannel::create([
            'team_id' => $team->id, 'type' => 'email', 'name' => 'Primary Email',
        ]);
        $rule = NotifyRule::create(array_merge([
            'team_id' => $team->id,
            'trigger_event' => 'schedule.weekly_digest',
            'template_id' => $template->id,
            'channel_id' => $channel->id,
            'is_active' => true,
        ], $ruleOverrides));

        return NotifySchedule::create(array_merge([
            'team_id' => $team->id,
            'rule_id' => $rule->id,
            'name' => 'Monday digest',
            'cron_expression' => '0 9 * * 1',
            'timezone' => 'UTC',
            'recipients' => ['a@example.test', 'b@example.test'],
            'is_active' => true,
        ], $scheduleOverrides));
    }

    public function test_it_creates_a_queued_notify_log_per_recipient_for_a_due_schedule(): void
    {
        Carbon::setTestNow('2026-08-10 09:00:00');
        $team = Team::factory()->create();
        $schedule = $this->makeRuleAndSchedule($team);

        $this->artisan('notify:dispatch-schedules')->assertExitCode(0);

        $this->assertSame(2, NotifyLog::where('team_id', $team->id)->count());
        $this->assertDatabaseHas('notify_logs', [
            'team_id' => $team->id,
            'recipient' => 'a@example.test',
            'status' => 'queued',
            'subject' => 'Your weekly digest',
        ]);
        $this->assertNotNull($schedule->fresh()->last_run_at);
        $this->assertNotNull($schedule->fresh()->next_run_at);
    }

    public function test_it_skips_a_schedule_that_is_not_due_yet(): void
    {
        Carbon::setTestNow('2026-08-10 10:00:00'); // an hour past the 09:00 match
        $team = Team::factory()->create();
        $this->makeRuleAndSchedule($team);

        $this->artisan('notify:dispatch-schedules')->assertExitCode(0);

        $this->assertSame(0, NotifyLog::where('team_id', $team->id)->count());
    }

    public function test_it_skips_an_inactive_schedule(): void
    {
        Carbon::setTestNow('2026-08-10 09:00:00');
        $team = Team::factory()->create();
        $this->makeRuleAndSchedule($team, scheduleOverrides: ['is_active' => false]);

        $this->artisan('notify:dispatch-schedules')->assertExitCode(0);

        $this->assertSame(0, NotifyLog::where('team_id', $team->id)->count());
    }

    public function test_it_skips_a_schedule_whose_rule_is_inactive(): void
    {
        Carbon::setTestNow('2026-08-10 09:00:00');
        $team = Team::factory()->create();
        $this->makeRuleAndSchedule($team, ruleOverrides: ['is_active' => false]);

        $this->artisan('notify:dispatch-schedules')->assertExitCode(0);

        $this->assertSame(0, NotifyLog::where('team_id', $team->id)->count());
    }

    public function test_it_skips_a_schedule_with_no_rule_attached(): void
    {
        Carbon::setTestNow('2026-08-10 09:00:00');
        $team = Team::factory()->create();
        NotifySchedule::factory()->create([
            'team_id' => $team->id,
            'rule_id' => null,
            'cron_expression' => '0 9 * * 1',
            'timezone' => 'UTC',
        ]);

        $this->artisan('notify:dispatch-schedules')->assertExitCode(0);

        $this->assertSame(0, NotifyLog::where('team_id', $team->id)->count());
    }

    public function test_it_does_not_double_dispatch_within_the_same_minute(): void
    {
        Carbon::setTestNow('2026-08-10 09:00:00');
        $team = Team::factory()->create();
        $this->makeRuleAndSchedule($team);

        $this->artisan('notify:dispatch-schedules')->assertExitCode(0);
        $this->artisan('notify:dispatch-schedules')->assertExitCode(0);

        $this->assertSame(2, NotifyLog::where('team_id', $team->id)->count());
    }

    public function test_it_dispatches_again_the_following_week(): void
    {
        Carbon::setTestNow('2026-08-10 09:00:00');
        $team = Team::factory()->create();
        $this->makeRuleAndSchedule($team);
        $this->artisan('notify:dispatch-schedules');

        Carbon::setTestNow('2026-08-17 09:00:00'); // the next Monday 09:00
        $this->artisan('notify:dispatch-schedules');

        $this->assertSame(4, NotifyLog::where('team_id', $team->id)->count());
    }
}
