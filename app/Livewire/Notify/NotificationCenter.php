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

    /**
     * No explicit team_id filter needed: NotifyLog's HasTeamScope trait
     * applies it automatically to every query against this model.
     */
    #[Computed]
    public function logs()
    {
        return NotifyLog::query()
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
