<div class="dot-card dot-card-pad">
    <div class="card-head">
        <h3 style="font-family:'Syne',sans-serif;font-size:0.875rem;font-weight:700;color:#f4f4f5;margin:0;">Channels</h3>
        @can('create', [\App\Models\NotifyChannel::class, auth()->user()->currentTeam])
        <button wire:click="$toggle('showForm')" class="dot-btn dot-btn-ghost" style="font-size:12px;padding:5px 12px;">
            {{ $showForm ? 'Cancel' : '+ Add Channel' }}
        </button>
        @endcan
    </div>

    @if($showForm)
    <form wire:submit="addChannel" class="form-grid form-grid-3">
        <div>
            <div style="font-size:10px;color:#52525b;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.08em;">Type</div>
            <select wire:model="type" class="dot-input">
                <option value="email">Email</option>
                <option value="sms">SMS</option>
                <option value="push">Push</option>
                <option value="webhook">Webhook</option>
                <option value="slack">Slack</option>
                <option value="in_app">In-App</option>
            </select>
            @error('type')<p style="font-size:11px;color:#ef4444;margin-top:3px;">{{ $message }}</p>@enderror
        </div>
        <div>
            <div style="font-size:10px;color:#52525b;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.08em;">Name</div>
            <input wire:model="name" type="text" placeholder="Primary Email" class="dot-input" />
            @error('name')<p style="font-size:11px;color:#ef4444;margin-top:3px;">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="dot-btn dot-btn-primary btn-tall">Add</button>
    </form>
    @endif

    @if($this->channels->isEmpty())
        <div style="text-align:center;padding:1.5rem 0;">
            <p style="font-size:0.8rem;color:#52525b;margin:0;">No channels configured yet.</p>
        </div>
    @else
        <div style="display:grid;gap:0.4rem;">
            @foreach($this->channels as $channel)
            <div class="dot-row">
                <div class="dot-row-main" style="display:flex;align-items:center;gap:0.5rem;">
                    <span class="material-symbols-rounded" style="font-size:15px;color:#71717a;">
                        @php
                            echo match($channel->type) {
                                'email'   => 'mail',
                                'sms'     => 'sms',
                                'push'    => 'notifications',
                                'webhook' => 'webhook',
                                'slack'   => 'tag',
                                default   => 'circle_notifications',
                            };
                        @endphp
                    </span>
                    <div style="min-width:0;">
                        <div class="row-break" style="font-size:12px;font-weight:600;color:#d4d4d8;">{{ $channel->name }}</div>
                        <div style="font-size:11px;color:#52525b;">{{ ucfirst($channel->type) }}</div>
                    </div>
                </div>
                <div class="dot-row-actions">
                    <span style="font-size:10px;font-weight:600;padding:2px 7px;border-radius:100px;{{ $channel->is_active ? 'background:rgba(34,197,94,0.1);color:#22c55e;' : 'background:rgba(255,255,255,0.06);color:#71717a;' }}">
                        {{ $channel->is_active ? 'Active' : 'Inactive' }}
                    </span>
                    @can('update', $channel)
                    <button wire:click="toggleChannel({{ $channel->id }})" class="dot-btn dot-btn-ghost" style="font-size:11px;padding:3px 8px;">Toggle</button>
                    @endcan
                    @can('delete', $channel)
                    <button wire:click="deleteChannel({{ $channel->id }})" class="row-action" style="background:none;border:none;color:#52525b;cursor:pointer;font-size:11px;padding:3px 8px;">Remove</button>
                    @endcan
                </div>
            </div>
            @endforeach
        </div>
    @endif
</div>
