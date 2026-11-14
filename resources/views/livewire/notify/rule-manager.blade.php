<div>
    <div class="card-head">
        <div>
            <h2 class="nf-h2">Routing Rules</h2>
            <p class="nf-sub">A rule sends one template through one channel when an event arrives.</p>
        </div>
        @can('create', [\App\Models\NotifyRule::class, auth()->user()->currentTeam])
        <button type="button" wire:click="{{ $showForm ? 'cancelEdit' : '$toggle(\'showForm\')' }}" class="dot-btn dot-btn-ghost">
            {{ $showForm ? 'Cancel' : '+ Add Rule' }}
        </button>
        @endcan
    </div>

    @if($showForm)
    <form wire:submit="saveRule" class="nf-form">
        <div class="form-grid form-grid-2">
            <div>
                <label class="nf-label" for="rule-event">Trigger Event</label>
                <input id="rule-event" wire:model="triggerEvent" type="text" placeholder="user.signup, payment.failed, custom.*" class="dot-input dot-input-code" @error('triggerEvent') aria-invalid="true" @enderror />
                @error('triggerEvent')<p class="nf-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="nf-label" for="rule-active">Active</label>
                <select id="rule-active" wire:model="isActive" class="dot-input">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div>
                <label class="nf-label" for="rule-template">Template</label>
                <select id="rule-template" wire:model="templateId" class="dot-input" @error('templateId') aria-invalid="true" @enderror>
                    <option value="">Select a template…</option>
                    @foreach($this->templateOptions as $template)
                        <option value="{{ $template->id }}">{{ $template->name }}</option>
                    @endforeach
                </select>
                @error('templateId')<p class="nf-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="nf-label" for="rule-channel">Channel</label>
                <select id="rule-channel" wire:model="channelId" class="dot-input" @error('channelId') aria-invalid="true" @enderror>
                    <option value="">Select a channel…</option>
                    @foreach($this->channelOptions as $channel)
                        <option value="{{ $channel->id }}">{{ $channel->name }} ({{ ucfirst($channel->type) }})</option>
                    @endforeach
                </select>
                @error('channelId')<p class="nf-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-span">
                <label class="nf-label" for="rule-conditions">Conditions (JSON, optional)</label>
                <textarea id="rule-conditions" wire:model="conditionsJson" rows="2" placeholder='{"amount_gt": 100}' class="dot-input dot-input-code" @error('conditionsJson') aria-invalid="true" @enderror></textarea>
                <p class="nf-help">Domain-event conditions only — a condition may never read a recipient-activity field (last_login, inactive_days, etc.). See wiki.md §5.</p>
                @error('conditionsJson')<p class="nf-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="nf-label" for="rule-ceiling">Rate Ceiling (per hour, optional)</label>
                <input id="rule-ceiling" wire:model="rateCeilingPerHour" type="number" min="1" placeholder="No ceiling" class="dot-input" @error('rateCeilingPerHour') aria-invalid="true" @enderror />
                @error('rateCeilingPerHour')<p class="nf-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-span">
                <button type="submit" class="dot-btn dot-btn-primary btn-tall" wire:loading.attr="disabled" wire:target="saveRule">{{ $editingId ? 'Update Rule' : 'Add Rule' }}</button>
            </div>
        </div>
    </form>
    @endif

    @if($this->rules->isEmpty())
        <p class="nf-empty">No routing rules yet. A rule maps a trigger event to a template + channel.</p>
    @else
        <div class="nf-rows">
            @foreach($this->rules as $rule)
            <div class="dot-row">
                <div class="dot-row-main">
                    <div class="row-break nf-row-title nf-code">{{ $rule->trigger_event }}</div>
                    <div class="row-break nf-row-meta">
                        Sends {{ $rule->template->name ?? 'deleted template' }} through {{ $rule->channel->name ?? 'deleted channel' }} ({{ ucfirst($rule->channel->type ?? '?') }}){{ $rule->rate_ceiling_per_hour ? ', capped at '.$rule->rate_ceiling_per_hour.' an hour' : '' }}.
                    </div>
                </div>
                <div class="dot-row-actions">
                    <x-status :kind="$rule->is_active ? 'on' : 'off'" :label="$rule->is_active ? 'Active' : 'Inactive'" />
                    @can('update', $rule)
                    <button type="button" wire:click="edit({{ $rule->id }})" class="dot-btn dot-btn-ghost dot-btn-small" aria-label="Edit {{ $rule->trigger_event }}">Edit</button>
                    <button type="button" wire:click="toggleRule({{ $rule->id }})" class="dot-btn dot-btn-ghost dot-btn-small" aria-label="Toggle {{ $rule->trigger_event }}">Toggle</button>
                    @endcan
                    @can('delete', $rule)
                    <button type="button" wire:click="deleteRule({{ $rule->id }})" class="row-action" aria-label="Remove {{ $rule->trigger_event }}">Remove</button>
                    @endcan
                </div>
            </div>
            @endforeach
        </div>
    @endif
</div>
