<?php

namespace App\Livewire\Analytics;

use App\Models\DataSource;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class DataSourcePanel extends Component
{
    public string $platform = '';
    public string $displayName = '';
    public string $baseUrl = '';
    public bool $showForm = false;

    protected array $rules = [
        'platform'    => 'required|string|max:50',
        'displayName' => 'required|string|max:100',
        'baseUrl'     => 'nullable|url|max:255',
    ];

    #[Computed]
    public function sources(): Collection
    {
        return DataSource::where('team_id', auth()->user()->currentTeam->id)
            ->orderBy('platform')
            ->get();
    }

    public function addSource(): void
    {
        $this->validate();

        DataSource::create([
            'team_id'      => auth()->user()->currentTeam->id,
            'platform'     => $this->platform,
            'display_name' => $this->displayName,
            'base_url'     => $this->baseUrl ?: null,
            'status'       => 'pending',
        ]);

        $this->reset(['platform', 'displayName', 'baseUrl', 'showForm']);
        unset($this->sources);
    }

    public function toggleStatus(int $id): void
    {
        $source = DataSource::where('team_id', auth()->user()->currentTeam->id)->findOrFail($id);
        $source->update([
            'status' => $source->status === 'connected' ? 'pending' : 'connected',
        ]);
        unset($this->sources);
    }

    public function deleteSource(int $id): void
    {
        DataSource::where('team_id', auth()->user()->currentTeam->id)->findOrFail($id)->delete();
        unset($this->sources);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.analytics.data-source-panel');
    }
}
