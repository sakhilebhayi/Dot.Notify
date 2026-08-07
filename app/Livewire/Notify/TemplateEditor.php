<?php

namespace App\Livewire\Notify;

use App\Models\NotifyTemplate;
use App\Models\Team;
use App\Services\AiNotifyService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class TemplateEditor extends Component
{
    public bool $showForm = false;

    public string $templateName = '';

    public string $purpose = '';

    public string $channelType = 'email';

    public string $generatedBody = '';

    public string $generatedSubject = '';

    /**
     * currentTeam is null for a user with no team (e.g. a freshly
     * registered user who hasn't created/joined one, or one removed from
     * their last team) — see NotifyDashboardTest's no-team coverage and
     * the platform-loop pass notes in wiki.md.
     */
    private function resolveCurrentTeam(): ?Team
    {
        return Auth::user()?->currentTeam;
    }

    /**
     * No explicit team_id filter needed: NotifyTemplate's HasTeamScope
     * trait applies it automatically to every query against this model.
     */
    #[Computed]
    public function templates()
    {
        return NotifyTemplate::orderByDesc('created_at')->get();
    }

    public function generate(): void
    {
        $this->validate([
            'purpose' => 'required|string|max:200',
            'channelType' => 'required|string',
        ]);

        $service = new AiNotifyService;
        $result = $service->generateTemplate($this->purpose, $this->channelType);

        $this->generatedSubject = $result['subject'] ?? '';
        $this->generatedBody = $result['body'] ?? '';
    }

    public function saveTemplate(): void
    {
        // Wire:click-reachable action on an already-loaded page (embedded
        // in the dashboard) — abort rather than redirect if the team
        // disappeared after render, matching the ecosystem's
        // "No active team selected." convention.
        $team = $this->resolveCurrentTeam();

        if (! $team) {
            abort(403, 'No active team selected.');
        }

        $this->validate([
            'templateName' => 'required|string|max:100',
            'generatedBody' => 'required|string',
        ]);

        NotifyTemplate::create([
            'team_id' => $team->id,
            'name' => $this->templateName,
            'subject' => $this->generatedSubject,
            'body' => $this->generatedBody,
            'channel_type' => $this->channelType,
        ]);

        $this->reset(['templateName', 'purpose', 'generatedBody', 'generatedSubject', 'showForm']);
        unset($this->templates);
    }

    public function render()
    {
        return view('livewire.notify.template-editor');
    }
}
