<?php

namespace Tests\Feature\Notify;

use App\Models\NotifyBatch;
use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the dashboard widgets added in the platform-loop pass: active/total
 * channel counts, the 30-day delivery success rate, and the recent-batches
 * list. See routes/web.php ('dashboard' route) and resources/views/dashboard.blade.php.
 */
class NotifyDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->withPersonalTeam()->create();
        $this->team = $this->user->currentTeam;
    }

    public function test_dashboard_shows_active_and_total_channel_counts(): void
    {
        NotifyChannel::create(['team_id' => $this->team->id, 'type' => 'email', 'name' => 'A', 'is_active' => true]);
        NotifyChannel::create(['team_id' => $this->team->id, 'type' => 'sms', 'name' => 'B', 'is_active' => false]);

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertViewHas('activeChannels', 1)
            ->assertViewHas('totalChannels', 2);
    }

    public function test_delivery_success_rate_is_null_with_no_resolved_logs(): void
    {
        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertViewHas('deliverySuccessRate', null);
    }

    public function test_delivery_success_rate_reflects_resolved_logs_only(): void
    {
        NotifyLog::create(['team_id' => $this->team->id, 'recipient' => 'a@example.com', 'status' => 'delivered']);
        NotifyLog::create(['team_id' => $this->team->id, 'recipient' => 'b@example.com', 'status' => 'delivered']);
        NotifyLog::create(['team_id' => $this->team->id, 'recipient' => 'c@example.com', 'status' => 'failed']);
        // Still in-flight, must not count toward the rate.
        NotifyLog::create(['team_id' => $this->team->id, 'recipient' => 'd@example.com', 'status' => 'queued']);

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertViewHas('deliverySuccessRate', 66.7);
    }

    public function test_dashboard_lists_recent_batches(): void
    {
        NotifyBatch::create([
            'team_id'          => $this->team->id,
            'name'             => 'Spring Promo',
            'status'           => 'completed',
            'total_recipients' => 50,
            'sent_count'       => 48,
        ]);

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Spring Promo')
            ->assertSee('Recent Batches');
    }

    public function test_dashboard_does_not_leak_other_teams_data(): void
    {
        $otherTeam = Team::forceCreate(['name' => 'Other', 'user_id' => User::factory()->create()->id, 'personal_team' => true]);
        NotifyChannel::create(['team_id' => $otherTeam->id, 'type' => 'email', 'name' => 'Not Mine', 'is_active' => true]);

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertViewHas('activeChannels', 0)
            ->assertDontSee('Not Mine');
    }

    /**
     * Platform-loop pass (2026-08-04): a user with no current team (e.g.
     * removed from their last team) must be redirected before any query
     * runs. HasTeamScope's global scope is a deliberate no-op when
     * currentTeam is null (see HasTeamScope's docblock) — without this
     * guard, an unguarded render wouldn't just null-deref, it would
     * silently return every team's rows.
     */
    public function test_authenticated_user_with_no_team_is_redirected_to_team_creation(): void
    {
        $user = User::factory()->create(['current_team_id' => null]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('teams.create'));
    }
}
