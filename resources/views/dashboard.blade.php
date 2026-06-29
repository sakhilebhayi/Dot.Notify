<x-app-layout>

<div style="padding:2rem 2.5rem 3rem;">

    {{-- Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;">
        <div>
            <h1 style="font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;color:#f4f4f5;margin:0 0 0.2rem;letter-spacing:-0.01em;">Intelligence Overview</h1>
            <p style="font-size:0.78rem;color:#52525b;margin:0;">{{ now()->format('l, F j, Y') }}</p>
        </div>
        <span style="display:inline-flex;align-items:center;gap:7px;background:rgba(14,165,233,0.1);border:1px solid rgba(14,165,233,0.2);color:#0ea5e9;border-radius:100px;padding:4px 12px;font-size:11px;font-weight:600;">
            <span style="width:6px;height:6px;border-radius:50%;background:#0ea5e9;animation:live-pulse 2.8s ease-in-out infinite;flex-shrink:0;"></span>
            Intelligence Engine Active
        </span>
    </div>

    {{-- KPI Strip --}}
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem;">
        <div class="dot-card" style="padding:1.25rem 1.5rem;">
            <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.75rem;">Connected Sources</div>
            <div class="metric-val" style="font-size:2rem;font-weight:600;color:var(--accent);">{{ $connectedCount }}</div>
        </div>
        <div class="dot-card" style="padding:1.25rem 1.5rem;">
            <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.75rem;">Open Alerts</div>
            <div class="metric-val" style="font-size:2rem;font-weight:600;color:#ef4444;">{{ $openAlertCount }}</div>
        </div>
        <div class="dot-card" style="padding:1.25rem 1.5rem;">
            <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.75rem;">Pending Actions</div>
            <div class="metric-val" style="font-size:2rem;font-weight:600;color:#f59e0b;">{{ $pendingRecommendationCount }}</div>
        </div>
        <div class="dot-card" style="padding:1.25rem 1.5rem;">
            <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.75rem;">AI Model</div>
            <div style="font-size:11px;font-weight:500;color:#71717a;font-family:'JetBrains Mono',monospace;margin-top:0.5rem;">claude-sonnet-4-6</div>
        </div>
    </div>

    {{-- Intelligence Query --}}
    <livewire:analytics.intelligence-dashboard />

    {{-- Alerts + Recommendations --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-top:1.25rem;">
        <livewire:analytics.alerts-panel />
        <livewire:analytics.recommendations-panel />
    </div>

    {{-- Data Sources --}}
    <div style="margin-top:1.25rem;">
        <livewire:analytics.data-source-panel />
    </div>

</div>

</x-app-layout>
