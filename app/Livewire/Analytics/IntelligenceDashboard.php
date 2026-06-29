<?php

namespace App\Livewire\Analytics;

use App\Models\AnalyticsAlert;
use App\Models\ComputedMetric;
use App\Models\DataSource;
use App\Models\Recommendation;
use App\Services\AiInsightService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class IntelligenceDashboard extends Component
{
    public string $intelligenceQuery = '';
    public string $queryAnswer = '';
    public bool $queryLoading = false;

    #[Computed]
    public function connectedSources(): Collection
    {
        return DataSource::where('team_id', auth()->user()->currentTeam->id)
            ->where('status', 'connected')
            ->get();
    }

    #[Computed]
    public function openAlerts(): Collection
    {
        return AnalyticsAlert::where('team_id', auth()->user()->currentTeam->id)
            ->where('status', 'open')
            ->orderBy('severity')
            ->orderByDesc('triggered_at')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function pendingRecommendations(): Collection
    {
        return Recommendation::where('team_id', auth()->user()->currentTeam->id)
            ->where('status', 'pending')
            ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
            ->limit(5)
            ->get();
    }

    public function askIntelligence(): void
    {
        $this->validate(['intelligenceQuery' => 'required|string|min:5|max:500']);

        $this->queryLoading = true;

        $service = new AiInsightService(
            apiKey: config('services.anthropic.key', ''),
        );

        $this->queryAnswer = $service->answerIntelligenceQuery(
            auth()->user()->currentTeam,
            $this->intelligenceQuery,
        );

        $this->queryLoading = false;
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.analytics.intelligence-dashboard');
    }
}
