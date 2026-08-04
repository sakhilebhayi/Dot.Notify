<?php

namespace App\Livewire\Notify;

use App\Models\NotifyChannel;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ChannelManager extends Component
{
    public bool $showForm = false;
    public string $type = 'email';
    public string $name = '';

    /**
     * currentTeam is null for a user with no team (e.g. a freshly
     * registered user who hasn't created/joined one, or one removed from
     * their last team) — see NotifyDashboardTest's no-team coverage and
     * the platform-loop pass notes in wiki.md.
     */
    private function resolveCurrentTeam(): ?Team
    {
        return Auth::user()?->currentTeam;
    }

    /**
     * No explicit team_id filter needed: NotifyChannel's HasTeamScope
     * trait applies it automatically to every query against this model.
     */
    #[Computed]
    public function channels()
    {
        return NotifyChannel::orderBy('type')->get();
    }

    public function addChannel(): void
    {
        // This is a wire:click-reachable action on an already-loaded page
        // (embedded in the dashboard), so we can't redirect mid-action if
        // the team disappeared after render — abort instead, matching the
        // ecosystem's "No active team selected." convention.
        $team = $this->resolveCurrentTeam();

        if (! $team) {
            abort(403, 'No active team selected.');
        }

        $this->validate([
            'type' => 'required|in:email,sms,push,webhook,slack,in_app',
            'name' => 'required|string|max:100',
        ]);

        NotifyChannel::create([
            'team_id' => $team->id,
            'type'    => $this->type,
            'name'    => $this->name,
        ]);

        $this->reset(['type', 'name', 'showForm']);
        unset($this->channels);
    }

    public function toggleChannel(int $id): void
    {
        $channel = NotifyChannel::findOrFail($id);
        $channel->update(['is_active' => ! $channel->is_active]);
        unset($this->channels);
    }

    public function deleteChannel(int $id): void
    {
        NotifyChannel::findOrFail($id)->delete();
        unset($this->channels);
    }

    public function render()
    {
        return view('livewire.notify.channel-manager');
    }
}
