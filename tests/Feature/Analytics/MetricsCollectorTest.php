<?php

namespace Tests\Feature\Analytics;

use App\Models\Team;
use App\Models\User;
use App\Services\Analytics\MetricsCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsCompanies;
use Tests\TestCase;

class MetricsCollectorTest extends TestCase
{
    use BuildsCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function collect(Team $team): array
    {
        return app(MetricsCollector::class)->collect($team);
    }

    public function test_it_counts_sent_failed_and_suppressed_messages_from_the_last_seven_days(): void
    {
        $team = $this->makeCompany();
        $this->makeLog($team, 'sent', '2026-11-04 10:00:00');
        $this->makeLog($team, 'sent', '2026-11-05 08:00:00');
        $this->makeLog($team, 'failed', null, '2026-11-03 10:00:00');
        $this->makeLog($team, 'suppressed', null, '2026-11-04 12:00:00');
        $this->makeLog($team, 'suppressed', null, '2026-11-04 13:00:00');
        $this->makeLog($team, 'suppressed', null, '2026-11-04 14:00:00');
        // Still in flight: none of the three.
        foreach (['queued', 'sending', 'retrying'] as $status) {
            $this->makeLog($team, $status, null, '2026-11-04 10:00:00');
        }

        $this->assertSame(['sent_7d' => 2, 'failed_7d' => 1, 'suppressed_7d' => 3], $this->collect($team)['metrics']);
    }

    public function test_the_seven_day_window_is_utc_inclusive_at_the_start_and_never_looks_ahead(): void
    {
        $team = $this->makeCompany();
        foreach (['2026-10-29 09:00:00' => true, '2026-10-29 08:59:59' => false, '2026-11-05 09:00:00' => true, '2026-11-05 09:00:01' => false] as $at => $inside) {
            $this->makeLog($team, 'sent', $at);
            $this->makeLog($team, 'failed', null, $at);
            $this->makeLog($team, 'suppressed', null, $at);
        }

        $this->assertSame(['sent_7d' => 2, 'failed_7d' => 2, 'suppressed_7d' => 2], $this->collect($team)['metrics']);
    }

    public function test_a_message_counts_when_it_was_sent_or_ended_not_when_it_was_queued(): void
    {
        $team = $this->makeCompany();
        // Queued weeks ago (created_at 2026-09-01), sent / failed / suppressed yesterday.
        $this->makeLog($team, 'sent', '2026-11-04 10:00:00');
        $this->makeLog($team, 'failed', null, '2026-11-04 10:00:00');
        $this->makeLog($team, 'suppressed', null, '2026-11-04 10:00:00');
        // Sent weeks ago, last touched weeks ago.
        $this->makeLog($team, 'sent', '2026-09-10 10:00:00');

        $this->assertSame(['sent_7d' => 1, 'failed_7d' => 1, 'suppressed_7d' => 1], $this->collect($team)['metrics']);
    }

    public function test_a_sent_row_with_no_sent_at_is_not_guessed_into_the_window(): void
    {
        $team = $this->makeCompany();
        $this->makeLog($team, 'sent', null, '2026-11-04 10:00:00');

        $this->assertSame(0, $this->collect($team)['metrics']['sent_7d']);
    }

    public function test_rows_from_before_the_status_clean_up_count_the_way_the_app_reads_them(): void
    {
        $team = $this->makeCompany();
        foreach (['delivered', 'opened', 'clicked'] as $legacy) {
            $this->makeLog($team, $legacy, '2026-11-04 10:00:00');
        }
        $this->makeLog($team, 'bounced', null, '2026-11-04 10:00:00');

        $metrics = $this->collect($team)['metrics'];

        $this->assertSame(3, $metrics['sent_7d'], 'the same set NotifyLog::wasSent() accepts');
        $this->assertSame(1, $metrics['failed_7d'], 'the same set NotifyLog::hasFailed() accepts');
    }

    public function test_company_a_numbers_never_include_company_b_rows(): void
    {
        $a = $this->makeCompany();
        $b = $this->makeCompany();
        $this->makeLog($a, 'sent', '2026-11-04 10:00:00');
        foreach (range(1, 4) as $i) {
            $this->makeLog($b, 'sent', '2026-11-04 10:00:00');
            $this->makeLog($b, 'failed', null, '2026-11-04 10:00:00');
        }
        $this->makeLog($b, 'suppressed', null, '2026-11-04 10:00:00');

        $this->assertSame(['sent_7d' => 1, 'failed_7d' => 0, 'suppressed_7d' => 0], $this->collect($a)['metrics']);
        $this->assertSame(['sent_7d' => 4, 'failed_7d' => 4, 'suppressed_7d' => 1], $this->collect($b)['metrics']);
    }

    public function test_the_numbers_do_not_depend_on_who_is_signed_in(): void
    {
        $a = $this->makeCompany();
        $b = $this->makeCompany();
        $this->makeLog($a, 'sent', '2026-11-04 10:00:00');
        $this->makeLog($b, 'sent', '2026-11-04 10:00:00');
        $this->makeLog($b, 'sent', '2026-11-04 11:00:00');

        $anonymous = $this->collect($b);

        Auth::login($a->owner);
        $asOther = $this->collect($b);
        Auth::logout();
        Auth::login(User::factory()->create(['current_team_id' => null]));
        $asTeamless = $this->collect($b);

        $this->assertSame(2, $anonymous['metrics']['sent_7d']);
        $this->assertSame($anonymous, $asOther);
        $this->assertSame($anonymous, $asTeamless);
    }

    public function test_an_empty_company_reports_true_zeros(): void
    {
        $this->assertSame(['metrics' => ['sent_7d' => 0, 'failed_7d' => 0, 'suppressed_7d' => 0], 'omitted' => []], $this->collect($this->makeCompany()));
    }

    public function test_only_company_teams_are_published_for(): void
    {
        $company = $this->makeCompany();
        $unlinked = $this->makeCompany();
        $unlinked->forceFill(['infodot_team_uuid' => null])->save();
        $blank = $this->makeCompany();
        $blank->forceFill(['infodot_team_uuid' => ''])->save();
        $personalLinked = Team::factory()->create(['personal_team' => true]);
        $personalLinked->forceFill(['infodot_team_uuid' => '11111111-2222-4333-8444-555555555555'])->save();

        $collector = app(MetricsCollector::class);

        $this->assertSame([$company->id], $collector->companies()->pluck('id')->all());
        $this->assertSame([$company->id], $collector->companies($company->infodot_team_uuid)->pluck('id')->all());
        $this->assertSame([], $collector->companies($personalLinked->infodot_team_uuid)->pluck('id')->all(), 'a personal team is never a company, even when linked');
    }

    public function test_it_only_ever_returns_keys_defined_in_the_analytics_contract(): void
    {
        $team = $this->makeCompany();
        $this->makeLog($team, 'sent', '2026-11-04 10:00:00');

        $metrics = $this->collect($team)['metrics'];

        // Dot.Analytics config/metrics.php, platforms.notify.metrics. No money, so no `currency`.
        $this->assertSame(['sent_7d', 'failed_7d', 'suppressed_7d'], array_keys($metrics));
        foreach ($metrics as $key => $value) {
            $this->assertIsInt($value, $key);
        }
    }

    public function test_the_collector_runs_one_query_whatever_the_log_size(): void
    {
        $team = $this->makeCompany();
        foreach (range(1, 25) as $i) {
            $this->makeLog($team, 'sent', '2026-11-04 10:00:00');
        }

        DB::enableQueryLog();
        $this->collect($team);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(1, $queries);
    }
}
