@php
    $selectedLog = $this->selectedLog;
    $logKind = fn ($log) => $log->wasSent() ? 'sent' : ($log->hasFailed() ? 'failed' : ($log->status === 'suppressed' ? 'off' : ($log->status === 'retrying' ? 'retrying' : 'waiting')));
    // How a row's status is ticked on the slip. Old rows may carry statuses nothing writes any more;
    // they fall in with the state they amount to (NotifyLog::wasSent / hasFailed).
    $slipState = match (true) {
        $selectedLog === null => null,
        $selectedLog->wasSent() => 'sent',
        $selectedLog->hasFailed() => 'failed',
        in_array($selectedLog->status, ['queued', 'sending', 'retrying', 'suppressed'], true) => $selectedLog->status,
        default => null,
    };
    $slipStates = ['queued' => 'Queued', 'sending' => 'Sending', 'retrying' => 'Retrying', 'sent' => 'Sent', 'failed' => 'Failed', 'suppressed' => 'Suppressed'];
    $reason = $selectedLog ? \App\Livewire\Notify\NotificationCenter::reason($selectedLog) : null;
@endphp
<div class="desk">
    <div class="desk-log">
        <div class="head">
            <h2 class="nf-h2" id="log-title">Notification log</h2>
            <span class="nf-more">The 20 newest messages</span>
        </div>
        <div class="toolbar">
            <div class="toolbar-search">
                <x-dot.filter-search name="search" wire:model.live.debounce.400ms="search" placeholder="Search recipient or subject…" label="Search recipient or subject" />
            </div>
            <select wire:model.live="filterStatus" class="dot-input toolbar-select" aria-label="Filter by status">
                <option value="">All statuses</option>
                <option value="queued">Queued</option>
                <option value="sending">Sending</option>
                <option value="retrying">Retrying</option>
                <option value="sent">Sent</option>
                <option value="failed">Failed</option>
                <option value="suppressed">Suppressed</option>
            </select>
        </div>
        <div wire:loading.delay wire:target="search,filterStatus" class="nf-loading" role="status">Loading…</div>
        <div wire:loading.remove.delay wire:target="search,filterStatus">
        @if($this->logs->isEmpty())
            @if($search || $filterStatus)
                <p class="nf-empty">No notifications match your filters.</p>
            @else
                <p class="nf-empty">No notifications sent yet. Configure a channel and rule to get started.</p>
            @endif
        @else
            <table class="nf-table" role="table" aria-labelledby="log-title">
                <thead>
                    <tr role="row">
                        <th scope="col">Recipient and subject</th>
                        <th scope="col">Event</th>
                        <th scope="col">Status</th>
                        <th scope="col">When</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($this->logs as $log)
                    <tr role="row" wire:key="log-{{ $log->id }}" @if($selectedLog && $selectedLog->is($log)) aria-current="true" @endif>
                        <td class="nf-cell-main" role="cell">
                            <a href="{{ route('dashboard', array_filter(['status' => $filterStatus, 'log' => $log->id])) }}#slip" class="row-break nf-row-title"
                               x-on:click.prevent="$wire.select({{ $log->id }}).then(() => { if (window.matchMedia('(max-width: 1099.98px)').matches) { document.getElementById('slip')?.scrollIntoView({ block: 'start' }); } })">{{ $log->recipient }}</a>
                            <small class="row-break">{{ $log->subject ?? 'No subject' }}</small>
                        </td>
                        <td class="nf-cell-event" role="cell">@if($log->event)<span class="nf-code">{{ $log->event }}</span>@else<span class="nf-soft">Not from an event</span>@endif</td>
                        <td class="nf-cell-status" role="cell"><x-status :kind="$logKind($log)" :label="ucfirst($log->status)" /></td>
                        <td class="nf-when" role="cell"><time datetime="{{ $log->created_at->toIso8601String() }}" title="{{ $log->created_at->format('j M Y H:i') }}">{{ $log->created_at->diffForHumans() }}</time></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
        </div>
    </div>

    <div class="desk-slip">
    @if($selectedLog)
        <section class="nf-slip" id="slip" aria-labelledby="slip-title" wire:key="slip-{{ $selectedLog->id }}">
            <div class="nf-slip-head">
                <h2 class="nf-slip-title" id="slip-title">Selected message</h2>
                <time class="nf-slip-time" datetime="{{ $selectedLog->created_at->toIso8601String() }}">{{ $selectedLog->created_at->format('j M Y, H:i') }}</time>
            </div>
            <dl class="nf-fields">
                <div class="nf-field"><dt>To</dt><dd>{{ $selectedLog->recipient }}</dd></div>
                <div class="nf-field"><dt>About</dt><dd @class(['is-blank' => ! $selectedLog->subject])>{{ $selectedLog->subject ?? 'No subject' }}</dd></div>
                <div class="nf-field"><dt>Event</dt><dd @class(['is-blank' => ! $selectedLog->event])>@if($selectedLog->event)<span class="nf-code">{{ $selectedLog->event }}</span>@else Not from a catalogued event @endif</dd></div>
                <div class="nf-field"><dt>Through</dt><dd @class(['is-blank' => ! $selectedLog->channel])>{{ $selectedLog->channel?->name ?? 'No channel recorded' }}</dd></div>
                <div class="nf-field"><dt>Rule</dt><dd @class(['is-blank' => ! $selectedLog->rule])>@if($selectedLog->rule)<span class="nf-code">{{ $selectedLog->rule->trigger_event }}</span>@else No rule recorded @endif</dd></div>
                <div class="nf-field"><dt>Attempts</dt><dd>{{ (int) $selectedLog->attempts }}</dd></div>
                @if($slipState === 'retrying' && $selectedLog->next_retry_at)
                    <div class="nf-field"><dt>Next attempt</dt><dd><time datetime="{{ $selectedLog->next_retry_at->toIso8601String() }}">{{ $selectedLog->next_retry_at->diffForHumans() }}</time></dd></div>
                @endif
                @if($reason && in_array($slipState, ['failed', 'retrying', 'suppressed'], true))
                    <div class="nf-field"><dt>{{ $slipState === 'failed' ? 'Why it failed' : ($slipState === 'suppressed' ? 'Why it was held back' : 'Last problem') }}</dt><dd class="nf-reason">{{ $reason }}</dd></div>
                @endif
            </dl>
            <ul class="nf-ticks" aria-label="Status">
                @foreach($slipStates as $state => $word)
                    <li @class(['nf-tick', 'is-on' => $slipState === $state]) @if($slipState === $state) aria-current="true" @endif>
                        <span class="nf-box"><svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="m3 8.5 3.2 3.2L13 4.8"/></svg></span>
                        {{ $word }}
                    </li>
                @endforeach
            </ul>
            <p class="nf-slip-note">
                @switch($slipState)
                    @case('sent') Sent means the channel accepted it. Dot.Notify is not told whether it was opened. @break
                    @case('failed') The channel refused it or could not be reached, and it will not be tried again. @break
                    @case('retrying') An attempt did not get through. It will be tried again automatically. @break
                    @case('suppressed') Not sent: this person has opted out of messages like this one. @break
                    @case('queued') Waiting its turn to be handed to the channel. @break
                    @case('sending') Being handed to the channel now. @break
                    @default Recorded as "{{ $selectedLog->status }}".
                @endswitch
                @if($selectedLog->channel && $selectedLog->channel->test_status === 'failed')
                    Its channel, {{ $selectedLog->channel->name }}, also failed its last test{{ $selectedLog->channel->last_tested_at ? ' '.$selectedLog->channel->last_tested_at->diffForHumans() : '' }}.
                @endif
            </p>
            @if($filterStatus !== 'failed' || ($selectedLog->rule && auth()->user()->can('update', $selectedLog->rule)))
            <div class="nf-slip-links">
                @if($filterStatus !== 'failed')
                    <a class="dot-btn dot-btn-ghost dot-btn-small" href="{{ route('dashboard', ['status' => 'failed']) }}#log">Show failed messages</a>
                @endif
                @if($selectedLog->rule)
                    @can('update', $selectedLog->rule)
                        <a class="dot-btn dot-btn-ghost dot-btn-small" href="#rules" wire:click="$dispatch('notify.edit-rule', { id: {{ $selectedLog->rule->id }} })">Edit the rule</a>
                    @endcan
                @endif
            </div>
            @endif
        </section>
    @else
        <section class="nf-slip" id="slip" aria-labelledby="slip-title">
            <div class="nf-slip-head"><h2 class="nf-slip-title" id="slip-title">Selected message</h2></div>
            <p class="nf-slip-empty">Nothing has been handed over for {{ auth()->user()->currentTeam->name }} yet. The first message will be shown here, with what became of it.</p>
        </section>
    @endif
    </div>
</div>
