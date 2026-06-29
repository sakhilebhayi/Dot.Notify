<?php

namespace App\Livewire\Notify;

use App\Models\NotifyChannel;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ChannelManager extends Component
{
    public bool $showForm = false;
    public string $type = 'email';
    public string $name = '';

    #[Computed]
    public function channels()
    {
        return NotifyChannel::where('team_id', auth()->user()->currentTeam->id)
            ->orderBy('type')
            ->get();
    }

    public function addChannel(): void
    {
        $this->validate([
            'type' => 'required|in:email,sms,push,webhook,slack,in_app',
            'name' => 'required|string|max:100',
        ]);

        NotifyChannel::create([
            'team_id' => auth()->user()->currentTeam->id,
            'type'    => $this->type,
            'name'    => $this->name,
        ]);

        $this->reset(['type', 'name', 'showForm']);
        unset($this->channels);
    }

    public function toggleChannel(int $id): void
    {
        $channel = NotifyChannel::where('team_id', auth()->user()->currentTeam->id)->findOrFail($id);
        $channel->update(['is_active' => ! $channel->is_active]);
        unset($this->channels);
    }

    public function deleteChannel(int $id): void
    {
        NotifyChannel::where('team_id', auth()->user()->currentTeam->id)->findOrFail($id)->delete();
        unset($this->channels);
    }

    public function render()
    {
        return view('livewire.notify.channel-manager');
    }
}
