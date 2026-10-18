<div class="dot-card dot-card-pad">
    <div class="card-head">
        <h3 style="font-family:'Syne',sans-serif;font-size:0.875rem;font-weight:700;color:#f4f4f5;margin:0;">Routing Rules</h3>
        @can('create', [\App\Models\NotifyRule::class, auth()->user()->currentTeam])
        <button wire:click="{{ $showForm ? 'cancelEdit' : '$toggle(\'showForm\')' }}" class="dot-btn dot-btn-ghost" style="font-size:12px;padding:5px 12px;">
            {{ $showForm ? 'Cancel' : '+ Add Rule' }}
        </button>
        @endcan
    </div>

    @if($showForm)
    <form wire:submit="saveRule" class="form-grid form-grid-2">
        <div>
            <div style="font-size:10px;color:#52525b;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.08em;">Trigger Event</div>
            <input wire:model="triggerEvent" type="text" placeholder="user.signup, payment.failed, custom.*" class="dot-input" />
            @error('triggerEvent')<p style="font-size:11px;color:#ef4444;margin-top:3px;">{{ $message }}</p>@enderror
        </div>
        <div>
            <div style="font-size:10px;color:#52525b;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.08em;">Active</div>
            <select wire:model="isActive" class="dot-input">
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
        </div>
        <div>
            <div style="font-size:10px;color:#52525b;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.08em;">Template</div>
            <select wire:model="templateId" class="dot-input">
                <option value="">Select a template…</option>
                @foreach($this->templateOptions as $template)
                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                @endforeach
            </select>
            @error('templateId')<p style="font-size:11px;color:#ef4444;margin-top:3px;">{{ $message }}</p>@enderror
        </div>
        <div>
            <div style="font-size:10px;color:#52525b;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.08em;">Channel</div>
            <select wire:model="channelId" class="dot-input">
                <option value="">Select a channel…</option>
                @foreach($this->channelOptions as $channel)
                    <option value="{{ $channel->id }}">{{ $channel->name }} ({{ ucfirst($channel->type) }})</option>
                @endforeach
            </select>
            @error('channelId')<p style="font-size:11px;color:#ef4444;margin-top:3px;">{{ $message }}</p>@enderror
        </div>
        <div class="form-span">
            <div style="font-size:10px;color:#52525b;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.08em;">Conditions (JSON, optional)</div>
            <textarea wire:model="conditionsJson" rows="2" placeholder='{"amount_gt": 100}' class="dot-input" style="width:100%;font-family:'Space Mono',monospace;font-size:11px;"></textarea>
            <p style="font-size:10px;color:#52525b;margin:4px 0 0;">Domain-event conditions only — a condition may never read a recipient-activity field (last_login, inactive_days, etc.). See wiki.md §5.</p>
            @error('conditionsJson')<p style="font-size:11px;color:#ef4444;margin-top:3px;">{{ $message }}</p>@enderror
        </div>
        <div>
            <div style="font-size:10px;color:#52525b;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.08em;">Rate Ceiling (per hour, optional)</div>
            <input wire:model="rateCeilingPerHour" type="number" min="1" placeholder="No ceiling" class="dot-input" />
            @error('rateCeilingPerHour')<p style="font-size:11px;color:#ef4444;margin-top:3px;">{{ $message }}</p>@enderror
        </div>
        <div class="form-span">
            <button type="submit" class="dot-btn dot-btn-primary btn-tall">{{ $editingId ? 'Update Rule' : 'Add Rule' }}</button>
        </div>
    </form>
    @endif

    @if($this->rules->isEmpty())
        <div style="text-align:center;padding:1.5rem 0;">
            <p style="font-size:0.8rem;color:#52525b;margin:0;">No routing rules yet. A rule maps a trigger event to a template + channel.</p>
        </div>
    @else
        <div style="display:grid;gap:0.4rem;">
            @foreach($this->rules as $rule)
            <div class="dot-row">
                <div class="dot-row-main">
                    <div class="row-break" style="font-size:12px;font-weight:600;color:#d4d4d8;font-family:'Space Mono',monospace;">{{ $rule->trigger_event }}</div>
                    <div style="font-size:11px;color:#52525b;">
                        {{ $rule->template->name ?? 'deleted template' }} → {{ $rule->channel->name ?? 'deleted channel' }} ({{ ucfirst($rule->channel->type ?? '?') }})
                        @if($rule->rate_ceiling_per_hour)
                            · capped at {{ $rule->rate_ceiling_per_hour }}/hr
                        @endif
                    </div>
                </div>
                <div class="dot-row-actions">
                    <span style="font-size:10px;font-weight:600;padding:2px 7px;border-radius:100px;{{ $rule->is_active ? 'background:rgba(34,197,94,0.1);color:#22c55e;' : 'background:rgba(255,255,255,0.06);color:#71717a;' }}">
                        {{ $rule->is_active ? 'Active' : 'Inactive' }}
                    </span>
                    @can('update', $rule)
                    <button wire:click="edit({{ $rule->id }})" class="dot-btn dot-btn-ghost" style="font-size:11px;padding:3px 8px;">Edit</button>
                    <button wire:click="toggleRule({{ $rule->id }})" class="dot-btn dot-btn-ghost" style="font-size:11px;padding:3px 8px;">Toggle</button>
                    @endcan
                    @can('delete', $rule)
                    <button wire:click="deleteRule({{ $rule->id }})" class="row-action" style="background:none;border:none;color:#52525b;cursor:pointer;font-size:11px;padding:3px 8px;">Remove</button>
                    @endcan
                </div>
            </div>
            @endforeach
        </div>
    @endif
</div>
