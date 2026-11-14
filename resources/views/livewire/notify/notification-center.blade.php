<div>
    <div class="card-head">
        <h2 class="nf-h2">Notification Log</h2>
        <div class="toolbar">
            <div class="toolbar-search">
                <x-icon name="search" />
                <input
                    type="search"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Search recipient or subject…"
                    class="dot-input"
                    aria-label="Search recipient or subject"
                >
            </div>
            <select wire:model.live="filterStatus" class="dot-input toolbar-select" aria-label="Filter by status">
                <option value="">All statuses</option>
                <option value="queued">Queued</option>
                <option value="sending">Sending</option>
                <option value="retrying">Retrying</option>
                <option value="sent">Sent</option>
                <option value="failed">Failed</option>
                <option value="suppressed">Suppressed</option>
            </select>
        </div>
    </div>
    <div wire:loading.delay wire:target="search,filterStatus" class="nf-loading" role="status">Loading…</div>
    <div wire:loading.remove.delay wire:target="search,filterStatus">
    @if($this->logs->isEmpty())
        @if($search || $filterStatus)
            <p class="nf-empty">No notifications match your filters.</p>
        @else
            <p class="nf-empty">No notifications sent yet. Configure a channel and rule to get started.</p>
        @endif
    @else
        <div class="nf-rows">
            @foreach($this->logs as $log)
            <div class="dot-row">
                <div class="dot-row-main">
                    <div class="row-break nf-row-title">{{ $log->recipient }}</div>
                    <div class="row-break nf-row-meta">{{ $log->subject ?? 'No subject' }}, <time datetime="{{ $log->created_at->toIso8601String() }}" title="{{ $log->created_at->format('j M Y H:i') }}">{{ $log->created_at->diffForHumans() }}</time></div>
                </div>
                <x-status :kind="$log->wasSent() ? 'sent' : ($log->hasFailed() ? 'failed' : ($log->status === 'suppressed' ? 'off' : ($log->status === 'retrying' ? 'retrying' : 'waiting')))" :label="ucfirst($log->status)" />
            </div>
            @endforeach
        </div>
    @endif
    </div>
</div>
