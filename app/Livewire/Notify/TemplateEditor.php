<?php

namespace App\Livewire\Notify;

use App\Models\NotifyTemplate;
use App\Models\Team;
use App\Services\AiNotifyService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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
        // The generator costs an AI call and only ever feeds saveTemplate(), which is
        // owner/admin only. Authorize it the same way so a read-only member (viewer
        // or editor) cannot spend it from a crafted Livewire call.
        $team = $this->resolveCurrentTeam();

        if (! $team) {
            abort(403, 'No active team selected.');
        }

        Gate::authorize('create', [NotifyTemplate::class, $team]);

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

        Gate::authorize('create', [NotifyTemplate::class, $team]);

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
