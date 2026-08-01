<?php

namespace App\Livewire\Notify;

use App\Models\NotifyLog;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificationCenter extends Component
{
    public string $filterStatus = '';
    public string $search = '';

    #[Computed]
    public function logs()
    {
        return NotifyLog::where('team_id', auth()->user()->currentTeam->id)
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('recipient', 'like', "%{$this->search}%")
                    ->orWhere('subject', 'like', "%{$this->search}%");
            }))
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
