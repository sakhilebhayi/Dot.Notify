<?php

namespace App\Livewire\Notify;

use App\Models\NotifyLog;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificationCenter extends Component
{
    public string $filterStatus = '';

    #[Computed]
    public function logs()
    {
        return NotifyLog::where('team_id', auth()->user()->currentTeam->id)
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
    }

    #[On('notify.sent')]
    public function refresh(): void
    {
        unset($this->logs);
    }

    public function render()
    {
        return view('livewire.notify.notification-center');
    }
}
