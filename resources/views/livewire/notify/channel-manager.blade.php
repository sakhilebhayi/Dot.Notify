<div>
    <div class="card-head">
        <h2 class="nf-h2">Channels</h2>
        @can('create', [\App\Models\NotifyChannel::class, auth()->user()->currentTeam])
        <button type="button" wire:click="$toggle('showForm')" class="dot-btn dot-btn-ghost">
            {{ $showForm ? 'Cancel' : '+ Add Channel' }}
        </button>
        @endcan
    </div>

    @if($showForm)
    <form wire:submit="addChannel" class="nf-form">
        <div class="form-grid form-grid-3">
            <div>
                <label class="nf-label" for="channel-type">Type</label>
                <select id="channel-type" wire:model="type" class="dot-input" @error('type') aria-invalid="true" @enderror>
                    <option value="email">Email</option>
                    <option value="sms">SMS</option>
                    <option value="push">Push</option>
                    <option value="webhook">Webhook</option>
                    <option value="slack">Slack</option>
                    <option value="in_app">In-App</option>
                </select>
                @error('type')<p class="nf-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="nf-label" for="channel-name">Name</label>
                <input id="channel-name" wire:model="name" type="text" placeholder="Primary Email" class="dot-input" @error('name') aria-invalid="true" @enderror />
                @error('name')<p class="nf-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="dot-btn dot-btn-primary btn-tall" wire:loading.attr="disabled" wire:target="addChannel">Add</button>
        </div>
    </form>
    @endif

    @if($this->channels->isEmpty())
        <p class="nf-empty">No channels configured yet.</p>
    @else
        <div class="nf-rows">
            @foreach($this->channels as $channel)
            @php $sent = $this->sentByChannel[$channel->id] ?? null; @endphp
            <div class="dot-row">
                <div class="dot-row-main nf-row-lead">
                    <x-icon :name="$channel->type" class="nf-row-icon" />
                    <div>
                        <div class="row-break nf-row-title">{{ $channel->name }}</div>
                        <div class="nf-row-meta">
                            {{ $channel->type === 'sms' ? 'SMS' : ucfirst(str_replace('_', '-', $channel->type)) }}.
                            @if($sent)
                                {{ $sent['count'] }} sent, the last <time datetime="{{ $sent['last']->toIso8601String() }}" title="{{ $sent['last']->format('j M Y H:i') }}">{{ $sent['last']->diffForHumans() }}</time>.
                            @else
                                Nothing sent through it yet.
                            @endif
                            @if($channel->test_status === 'failed')
                                Its last test failed{{ $channel->last_tested_at ? ' '.$channel->last_tested_at->diffForHumans() : '' }}.
                            @elseif($channel->test_status === 'ok' && $channel->last_tested_at)
                                Tested and working {{ $channel->last_tested_at->diffForHumans() }}.
                            @endif
                        </div>
                    </div>
                </div>
                <div class="dot-row-actions">
                    <x-status :kind="$channel->is_active ? 'on' : 'off'" :label="$channel->is_active ? 'Active' : 'Inactive'" />
                    @can('update', $channel)
                    <button type="button" wire:click="toggleChannel({{ $channel->id }})" class="dot-btn dot-btn-ghost dot-btn-small" aria-label="Toggle {{ $channel->name }}">Toggle</button>
                    @endcan
                    @can('delete', $channel)
                    <button type="button" wire:click="deleteChannel({{ $channel->id }})" class="row-action" aria-label="Remove {{ $channel->name }}">Remove</button>
                    @endcan
                </div>
            </div>
            @endforeach
        </div>
    @endif
</div>
