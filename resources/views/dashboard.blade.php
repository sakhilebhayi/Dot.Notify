<x-app-layout>

<div style="padding:2rem 2.5rem 3rem;">

    {{-- Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;">
        <div>
            <h1 style="font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;color:#f4f4f5;margin:0 0 0.2rem;letter-spacing:-0.01em;">Notification Hub</h1>
            <p style="font-size:0.78rem;color:#52525b;margin:0;">{{ now()->format('l, F j, Y') }}</p>
        </div>
    </div>

    {{-- KPI Strip --}}
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem;">
        <div class="dot-card" style="padding:1.25rem 1.5rem;">
            <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.75rem;">Active Channels</div>
            <div class="metric-val" style="font-size:2rem;font-weight:600;color:var(--accent);">{{ $activeChannels }}</div>
        </div>
        <div class="dot-card" style="padding:1.25rem 1.5rem;">
            <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.75rem;">Sent Today</div>
            <div class="metric-val" style="font-size:2rem;font-weight:600;color:#22c55e;">{{ $sentToday }}</div>
        </div>
        <div class="dot-card" style="padding:1.25rem 1.5rem;">
            <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.75rem;">Failed Today</div>
            <div class="metric-val" style="font-size:2rem;font-weight:600;color:#ef4444;">{{ $failedToday }}</div>
        </div>
        <div class="dot-card" style="padding:1.25rem 1.5rem;">
            <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.75rem;">Templates</div>
            <div class="metric-val" style="font-size:2rem;font-weight:600;color:var(--accent);">{{ $templateCount }}</div>
        </div>
    </div>

    {{-- Notification Log --}}
    <livewire:notify.notification-center />

    {{-- Channels + Templates --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-top:1.25rem;">
        <livewire:notify.channel-manager />
        <livewire:notify.template-editor />
    </div>

</div>

</x-app-layout>
