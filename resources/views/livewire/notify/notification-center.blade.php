<div class="dot-card dot-card-pad">
    <div class="card-head">
        <h3 style="font-family:'Syne',sans-serif;font-size:0.875rem;font-weight:700;color:#f4f4f5;margin:0;">Notification Log</h3>
        <div class="toolbar">
            <div class="toolbar-search">
                <span class="material-symbols-rounded" aria-hidden="true" style="position:absolute;left:8px;top:50%;transform:translateY(-50%);font-size:14px;color:#52525b;pointer-events:none;">search</span>
                <input
                    type="text"
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
    <div wire:loading.delay wire:target="search,filterStatus" style="text-align:center;padding:1rem 0;">
        <span style="font-size:11px;color:#52525b;">Loading…</span>
    </div>
    <div wire:loading.remove.delay wire:target="search,filterStatus">
    @if($this->logs->isEmpty())
        <div style="text-align:center;padding:2rem 0;">
            <span class="material-symbols-rounded" style="font-size:36px;color:#3f3f46;display:block;margin-bottom:0.75rem;">notifications_none</span>
            @if($search || $filterStatus)
                <p style="font-size:0.8rem;color:#52525b;margin:0;">No notifications match your filters.</p>
            @else
                <p style="font-size:0.8rem;color:#52525b;margin:0;">No notifications sent yet. Configure a channel and rule to get started.</p>
            @endif
        </div>
    @else
        <div style="display:grid;gap:0.4rem;">
            @foreach($this->logs as $log)
            <div class="dot-row">
                <div class="dot-row-main">
                    <div class="row-break" style="font-size:12px;font-weight:600;color:#d4d4d8;">{{ $log->recipient }}</div>
                    <div style="font-size:11px;color:#52525b;">{{ $log->subject ?? 'No subject' }} · {{ $log->created_at->diffForHumans() }}</div>
                </div>
                <span style="font-size:10px;font-weight:600;padding:2px 7px;border-radius:100px;flex-shrink:0;{{ $log->wasSent() ? 'background:rgba(34,197,94,0.1);color:#22c55e;' : ($log->hasFailed() ? 'background:rgba(239,68,68,0.1);color:#ef4444;' : 'background:rgba(245,158,11,0.1);color:#f59e0b;') }}">
                    {{ ucfirst($log->status) }}
                </span>
            </div>
            @endforeach
        </div>
    @endif
    </div>
</div>
