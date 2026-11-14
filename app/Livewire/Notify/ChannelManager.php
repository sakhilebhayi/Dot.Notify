<?php

namespace App\Livewire\Notify;

use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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
        return NotifyChannel::with('team')->orderBy('type')->get();
    }

    /**
     * What each channel has really handed over, from the log: how many messages
     * it accepted and when the last one was. A channel missing from this list has
     * never sent anything, and the page says so. NotifyLog's HasTeamScope keeps it
     * to the current team.
     *
     * @return array<int, array{count: int, last: Carbon}>
     */
    #[Computed]
    public function sentByChannel(): array
    {
        return NotifyLog::query()
            ->where('status', 'sent')
            ->whereNotNull('channel_id')
            ->whereNotNull('sent_at')
            ->selectRaw('channel_id, count(*) as sent_count, max(sent_at) as last_sent_at')
            ->groupBy('channel_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->channel_id => [
                'count' => (int) $row->sent_count,
                'last' => Carbon::parse($row->last_sent_at),
            ]])
            ->all();
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

        Gate::authorize('create', [NotifyChannel::class, $team]);

        $this->validate([
            'type' => 'required|in:email,sms,push,webhook,slack,in_app',
            'name' => 'required|string|max:100',
        ]);

        NotifyChannel::create([
            'team_id' => $team->id,
            'type' => $this->type,
            'name' => $this->name,
        ]);

        $this->reset(['type', 'name', 'showForm']);
        unset($this->channels);
    }

    public function toggleChannel(int $id): void
    {
        $channel = NotifyChannel::findOrFail($id);
        Gate::authorize('update', $channel);
        $channel->update(['is_active' => ! $channel->is_active]);
        unset($this->channels);
    }

    public function deleteChannel(int $id): void
    {
        $channel = NotifyChannel::findOrFail($id);
        Gate::authorize('delete', $channel);
        $channel->delete();
        unset($this->channels);
    }

    public function render()
    {
        return view('livewire.notify.channel-manager');
    }
}
