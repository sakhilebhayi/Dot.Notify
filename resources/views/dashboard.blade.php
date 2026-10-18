<x-app-layout>

<div class="page">

    {{-- Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;">
        <div>
            <h1 style="font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;color:#f4f4f5;margin:0 0 0.2rem;letter-spacing:-0.01em;">Notification Hub</h1>
            <p style="font-size:0.78rem;color:#52525b;margin:0;">{{ now()->format('l, F j, Y') }}</p>
        </div>
    </div>

    {{-- KPI Strip --}}
    <div class="kpi-grid">
        <div class="dot-card kpi-card">
            <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.75rem;">Active Channels</div>
            <div class="metric-val" style="font-size:2rem;font-weight:600;color:var(--accent);">{{ $activeChannels }}<span style="font-size:0.95rem;color:#52525b;font-weight:500;">/{{ $totalChannels }}</span></div>
        </div>
        <div class="dot-card kpi-card">
            <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.75rem;">Sent Today</div>
            <div class="metric-val" style="font-size:2rem;font-weight:600;color:#22c55e;">{{ $sentToday }}</div>
        </div>
        <div class="dot-card kpi-card">
            <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.75rem;">Failed Today</div>
            <div class="metric-val" style="font-size:2rem;font-weight:600;color:#ef4444;">{{ $failedToday }}</div>
        </div>
        <div class="dot-card kpi-card">
            <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.75rem;">Retrying</div>
            <div class="metric-val" style="font-size:2rem;font-weight:600;color:{{ $retrying > 0 ? '#f59e0b' : '#52525b' }};">{{ $retrying }}</div>
        </div>
        <div class="dot-card kpi-card">
            <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.75rem;">Suppressed (30d)</div>
            <div class="metric-val" style="font-size:2rem;font-weight:600;color:var(--accent);">{{ $suppressed30d }}</div>
        </div>
    </div>

    {{-- Notification Log --}}
    <livewire:notify.notification-center />

    {{-- Recent Batches --}}
    <div class="dot-card dot-card-pad" style="margin-top:1.25rem;">
        <h3 style="font-family:'Syne',sans-serif;font-size:0.875rem;font-weight:700;color:#f4f4f5;margin:0 0 1.25rem;">Recent Batches</h3>
        @if($recentBatches->isEmpty())
            <div style="text-align:center;padding:2rem 0;">
                <span class="material-symbols-rounded" style="font-size:36px;color:#3f3f46;display:block;margin-bottom:0.75rem;">layers</span>
                <p style="font-size:0.8rem;color:#52525b;margin:0;">No bulk sends yet. Batches you create will show up here with how many were sent.</p>
            </div>
        @else
            <div style="display:grid;gap:0.4rem;">
                @foreach($recentBatches as $batch)
                <div class="dot-row">
                    <div class="dot-row-main">
                        <div class="row-break" style="font-size:12px;font-weight:600;color:#d4d4d8;">{{ $batch->name }}</div>
                        <div style="font-size:11px;color:#52525b;">{{ $batch->sent_count }}/{{ $batch->total_recipients }} sent · {{ $batch->deliveryRate() }}% sent · {{ $batch->created_at->diffForHumans() }}</div>
                    </div>
                    <span style="font-size:10px;font-weight:600;padding:2px 7px;border-radius:100px;flex-shrink:0;{{ $batch->status === 'completed' ? 'background:rgba(34,197,94,0.1);color:#22c55e;' : ($batch->status === 'failed' ? 'background:rgba(239,68,68,0.1);color:#ef4444;' : 'background:rgba(245,158,11,0.1);color:#f59e0b;') }}">
                        {{ ucfirst($batch->status) }}
                    </span>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Channels + Templates --}}
    <div class="two-col">
        <livewire:notify.channel-manager />
        <livewire:notify.template-editor />
    </div>

    {{-- Routing Rules --}}
    <div style="margin-top:1.25rem;">
        <livewire:notify.rule-manager />
    </div>

</div>

</x-app-layout>
