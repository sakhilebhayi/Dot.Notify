<?php

namespace App\Livewire\Analytics;

use App\Models\Recommendation;
use App\Services\AiInsightService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class RecommendationsPanel extends Component
{
    public bool $generating = false;

    #[Computed]
    public function recommendations(): Collection
    {
        return Recommendation::where('team_id', auth()->user()->currentTeam->id)
            ->where('status', 'pending')
            ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
            ->get();
    }

    public function generate(): void
    {
        $this->generating = true;

        $team    = auth()->user()->currentTeam;
        $service = new AiInsightService(
            apiKey: config('services.anthropic.key', ''),
        );

        $result = $service->generateRecommendations($team, "Team: {$team->name}");

        foreach ($result['recommendations'] as $rec) {
            Recommendation::create([
                'team_id'  => $team->id,
                'engine'   => $rec['engine'] ?? 'decision',
                'title'    => $rec['title'],
                'rationale' => $rec['rationale'],
                'priority' => $rec['priority'] ?? 'medium',
                'status'   => 'pending',
            ]);
        }

        unset($this->recommendations);
        $this->generating = false;
    }

    public function action(int $id): void
    {
        Recommendation::where('team_id', auth()->user()->currentTeam->id)
            ->findOrFail($id)
            ->update(['status' => 'actioned']);
        unset($this->recommendations);
    }

    public function dismiss(int $id): void
    {
        Recommendation::where('team_id', auth()->user()->currentTeam->id)
            ->findOrFail($id)
            ->update(['status' => 'dismissed']);
        unset($this->recommendations);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.analytics.recommendations-panel');
    }
}
