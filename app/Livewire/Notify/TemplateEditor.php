<?php

namespace App\Livewire\Notify;

use App\Models\NotifyTemplate;
use App\Services\AiNotifyService;
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
            'purpose'     => 'required|string|max:200',
            'channelType' => 'required|string',
        ]);

        $service = new AiNotifyService();
        $result  = $service->generateTemplate($this->purpose, $this->channelType);

        $this->generatedSubject = $result['subject'] ?? '';
        $this->generatedBody    = $result['body'] ?? '';
    }

    public function saveTemplate(): void
    {
        $this->validate([
            'templateName'  => 'required|string|max:100',
            'generatedBody' => 'required|string',
        ]);

        NotifyTemplate::create([
            'team_id'      => auth()->user()->currentTeam->id,
            'name'         => $this->templateName,
            'subject'      => $this->generatedSubject,
            'body'         => $this->generatedBody,
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
