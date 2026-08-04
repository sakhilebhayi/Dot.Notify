<?php

namespace Tests\Feature\Notify;

use App\Livewire\Notify\ChannelManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Platform-loop pass (2026-08-04): app/Livewire/Notify/ChannelManager.php's
 * addChannel() used to dereference auth()->user()->currentTeam->id with no
 * null check. currentTeam is null for a user with no team (e.g. removed
 * from their last team) — reachable here because this component is
 * embedded on /dashboard, a wire:click action method on an already-loaded
 * page, so it can't redirect mid-action; it aborts(403) instead, matching
 * the ecosystem's "No active team selected." convention.
 */
class ChannelManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_channel_succeeds_with_an_active_team(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        Livewire::actingAs($user)
            ->test(ChannelManager::class)
            ->set('type', 'email')
            ->set('name', 'Ops Alerts')
            ->call('addChannel')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notify_channels', [
            'team_id' => $user->currentTeam->id,
            'name'    => 'Ops Alerts',
        ]);
    }

    public function test_add_channel_aborts_when_user_has_no_active_team(): void
    {
        $user = User::factory()->create(['current_team_id' => null]);

        Livewire::actingAs($user)
            ->test(ChannelManager::class)
            ->set('type', 'email')
            ->set('name', 'Ops Alerts')
            ->call('addChannel')
            ->assertStatus(403);

        $this->assertDatabaseMissing('notify_channels', ['name' => 'Ops Alerts']);
    }
}
