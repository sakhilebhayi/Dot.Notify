<?php

namespace Tests\Feature\Notify;

use App\Livewire\Notify\NotificationCenter;
use App\Models\NotifyLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the recipient/subject search added to the delivery log Livewire
 * component (platform-loop pass) alongside the pre-existing status filter.
 */
class NotifyLogSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->withPersonalTeam()->create();
        $this->team = $this->user->currentTeam;

        NotifyLog::create(['team_id' => $this->team->id, 'recipient' => 'alice@example.com', 'subject' => 'Welcome', 'status' => 'delivered']);
        NotifyLog::create(['team_id' => $this->team->id, 'recipient' => 'bob@example.com', 'subject' => 'Invoice due', 'status' => 'failed']);
    }

    public function test_search_filters_by_recipient(): void
    {
        Livewire::actingAs($this->user)
            ->test(NotificationCenter::class)
            ->set('search', 'alice')
            ->assertSee('alice@example.com')
            ->assertDontSee('bob@example.com');
    }

    public function test_search_filters_by_subject(): void
    {
        Livewire::actingAs($this->user)
            ->test(NotificationCenter::class)
            ->set('search', 'Invoice')
            ->assertSee('bob@example.com')
            ->assertDontSee('alice@example.com');
    }

    public function test_status_filter_still_works_alongside_search(): void
    {
        Livewire::actingAs($this->user)
            ->test(NotificationCenter::class)
            ->set('filterStatus', 'failed')
            ->assertSee('bob@example.com')
            ->assertDontSee('alice@example.com');
    }

    public function test_no_matches_shows_filtered_empty_state(): void
    {
        Livewire::actingAs($this->user)
            ->test(NotificationCenter::class)
            ->set('search', 'nobody-matches-this')
            ->assertSee('No notifications match your filters.');
    }
}
