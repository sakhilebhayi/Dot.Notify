<div class="dot-card" style="padding:1.5rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
        <h3 style="font-family:'Syne',sans-serif;font-size:0.875rem;font-weight:700;color:#f4f4f5;margin:0;">Notification Log</h3>
        <select wire:model.live="filterStatus" class="dot-input" style="font-size:11px;padding:4px 8px;width:auto;">
            <option value="">All</option>
            <option value="sent">Sent</option>
            <option value="delivered">Delivered</option>
            <option value="failed">Failed</option>
            <option value="opened">Opened</option>
        </select>
    </div>
    @if($this->logs->isEmpty())
        <div style="text-align:center;padding:2rem 0;">
            <span class="material-symbols-rounded" style="font-size:36px;color:#3f3f46;display:block;margin-bottom:0.75rem;">notifications_none</span>
            <p style="font-size:0.8rem;color:#52525b;margin:0;">No notifications sent yet. Configure a channel and rule to get started.</p>
        </div>
    @else
        <div style="display:grid;gap:0.4rem;">
            @foreach($this->logs as $log)
            <div style="display:flex;align-items:center;justify-content:space-between;padding:0.65rem 0.75rem;background:rgba(255,255,255,0.03);border-radius:8px;">
                <div style="min-width:0;flex:1;">
                    <div style="font-size:12px;font-weight:600;color:#d4d4d8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $log->recipient }}</div>
                    <div style="font-size:11px;color:#52525b;">{{ $log->subject ?? 'No subject' }} · {{ $log->created_at->diffForHumans() }}</div>
                </div>
                <span style="font-size:10px;font-weight:600;padding:2px 7px;border-radius:100px;flex-shrink:0;margin-left:0.5rem;{{ $log->wasDelivered() ? 'background:rgba(34,197,94,0.1);color:#22c55e;' : ($log->hasFailed() ? 'background:rgba(239,68,68,0.1);color:#ef4444;' : 'background:rgba(245,158,11,0.1);color:#f59e0b;') }}">
                    {{ ucfirst($log->status) }}
                </span>
            </div>
            @endforeach
        </div>
    @endif
</div>
