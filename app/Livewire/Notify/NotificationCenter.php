<?php

namespace App\Livewire\Notify;

use App\Models\NotifyLog;
use App\Services\Notify\Redactor;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class NotificationCenter extends Component
{
    public string $filterStatus = '';

    public string $search = '';

    /**
     * The message shown on the slip (?log=ID). Only ever honoured when it is one of the rows on show, which are
     * this company's alone; anything else falls back to the default (newest failed, else newest).
     */
    #[Url(as: 'log', history: false)]
    public ?int $selected = null;

    /**
     * No explicit team_id filter needed: NotifyLog's HasTeamScope trait
     * applies it automatically to every query against this model.
     */
    #[Computed]
    public function logs()
    {
        return NotifyLog::query()
            ->with(['channel', 'rule'])
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('recipient', 'like', "%{$this->search}%")
                    ->orWhere('subject', 'like', "%{$this->search}%");
            }))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function selectedLog(): ?NotifyLog
    {
        $logs = $this->logs;

        return ($this->selected !== null ? $logs->firstWhere('id', $this->selected) : null)
            ?? $logs->first(fn (NotifyLog $log) => $log->hasFailed())
            ?? $logs->first();
    }

    public function select(int $id): void
    {
        $this->selected = $this->logs->contains('id', $id) ? $id : null;
        unset($this->selectedLog);
    }

    /** The message's own reason, with anything secret-shaped scrubbed before it reaches the page. */
    public static function reason(NotifyLog $log): ?string
    {
        $reason = $log->failure_reason ?: $log->last_error;

        return $reason ? Redactor::scrubError($reason) : null;
    }

    #[On('notify.sent')]
    public function refresh(): void
    {
        unset($this->logs, $this->selectedLog);
    }

    public function render()
    {
        return view('livewire.notify.notification-center');
    }
}
