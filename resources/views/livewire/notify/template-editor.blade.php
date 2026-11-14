<div>
    <div class="card-head">
        <h2 class="nf-h2">Templates</h2>
        @can('create', [\App\Models\NotifyTemplate::class, auth()->user()->currentTeam])
        <button type="button" wire:click="$toggle('showForm')" class="dot-btn dot-btn-ghost">
            <x-icon name="draft" />
            {{ $showForm ? 'Cancel' : 'AI Generate' }}
        </button>
        @endcan
    </div>

    @if($showForm)
    <div class="nf-form">
        <div class="form-grid form-grid-2">
            <div>
                <label class="nf-label" for="template-purpose">Purpose</label>
                <input id="template-purpose" wire:model="purpose" type="text" placeholder="Payment failed notification" class="dot-input" @error('purpose') aria-invalid="true" @enderror />
                @error('purpose')<p class="nf-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="nf-label" for="template-channel">Channel</label>
                <select id="template-channel" wire:model="channelType" class="dot-input">
                    <option value="email">Email</option>
                    <option value="sms">SMS</option>
                    <option value="push">Push</option>
                    <option value="slack">Slack</option>
                </select>
            </div>
            <div class="form-span">
                <button type="button" wire:click="generate" class="dot-btn dot-btn-primary btn-tall" wire:loading.attr="disabled" wire:target="generate">
                    <span wire:loading.remove wire:target="generate">Generate with AI</span>
                    <span wire:loading wire:target="generate">Generating...</span>
                </button>
            </div>
        </div>

        @if($generatedBody)
        <div class="form-grid">
            <div>
                <label class="nf-label" for="template-name">Template Name</label>
                <input id="template-name" wire:model="templateName" type="text" class="dot-input" placeholder="Name this template..." @error('templateName') aria-invalid="true" @enderror />
                @error('templateName')<p class="nf-error">{{ $message }}</p>@enderror
            </div>
            <div>
                @if($generatedSubject)
                <p class="nf-draft-subject">Subject: {{ $generatedSubject }}</p>
                @endif
                <div class="nf-draft">{{ $generatedBody }}</div>
                <button type="button" wire:click="saveTemplate" class="dot-btn dot-btn-primary btn-tall" wire:loading.attr="disabled" wire:target="saveTemplate">Save Template</button>
            </div>
        </div>
        @endif
    </div>
    @endif

    @if($this->templates->isEmpty())
        <p class="nf-empty">No templates yet.@can('create', [\App\Models\NotifyTemplate::class, auth()->user()->currentTeam]) Use AI to generate your first one.@endcan</p>
    @else
        <div class="nf-rows">
            @foreach($this->templates as $tpl)
            <div class="dot-row">
                <div class="dot-row-main">
                    <div class="row-break nf-row-title">{{ $tpl->name }}</div>
                    <div class="nf-row-meta">{{ ucfirst($tpl->channel_type ?? 'any') }}, updated {{ $tpl->updated_at->diffForHumans() }}</div>
                </div>
                <x-status :kind="$tpl->is_active ? 'on' : 'off'" :label="$tpl->is_active ? 'Active' : 'Inactive'" />
            </div>
            @endforeach
        </div>
    @endif
</div>
