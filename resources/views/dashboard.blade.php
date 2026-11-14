<x-app-layout>
<x-slot name="header">Dashboard</x-slot>

@php
    $channelWord = Str::plural('channel', $totalChannels);
    $sending = $stage === 'sending';
@endphp

<div class="page">

    {{-- Where things stand: the state said once, the one next step, and the way back to InfoDot. --}}
    <section class="arrive" aria-label="Where things stand">
        <img class="nf-logo nf-logo-light" src="{{ asset('images/logo.png') }}" alt="Dot.Notify" width="1181" height="886">
        <img class="nf-logo nf-logo-dark" src="{{ asset('images/logo-light.png') }}" alt="Dot.Notify" width="1181" height="886">

        <div class="arrive-say">
            <h1 class="nf-title">
                @switch($stage)
                    @case('no_channel')
                        {{ $companyName }} has no channel yet, so nothing can be sent.
                        @break
                    @case('channels_off')
                        {{ $totalChannels === 1 ? 'Your one channel is' : "All {$totalChannels} channels are" }} switched off, so nothing can be sent.
                        @break
                    @case('no_rule')
                        No rule is switched on, so nothing is sent yet.
                        @break
                    @default
                        @if($failedToday > 0)
                            {{ $failedToday }} {{ Str::plural('message', $failedToday) }} failed today.
                        @elseif($sentToday > 0)
                            {{ $sentToday }} {{ Str::plural('message', $sentToday) }} sent today, none failed.
                        @elseif($lastSentAt)
                            Nothing has been sent today.
                        @else
                            Your channels and rules are ready, and nothing has been sent yet.
                        @endif
                @endswitch
            </h1>
            <p class="nf-say">
                @switch($stage)
                    @case('no_channel')
                        @if($canManage)
                            Start with an email channel: the events your Dot apps send go out by email.
                        @else
                            An owner or administrator adds channels. You can see what each app is able to send.
                        @endif
                        @break
                    @case('channels_off')
                        @if($canManage)
                            Switch one back on under Channels.
                        @else
                            An owner or administrator can switch one back on.
                        @endif
                        @break
                    @case('no_rule')
                        @if($canManage)
                            You have {{ $activeChannels }} working {{ Str::plural('channel', $activeChannels) }}. Choose the events people should be told about.
                        @else
                            An owner or administrator chooses which events are sent. You can see the list.
                        @endif
                        @break
                    @default
                        @if($failedToday > 0)
                            {{ $sentToday }} sent today.
                        @elseif($sentToday > 0)
                            The last message went out {{ \Illuminate\Support\Carbon::parse($lastSentAt)->diffForHumans() }}.
                        @elseif($lastSentAt)
                            The last message went out {{ \Illuminate\Support\Carbon::parse($lastSentAt)->diffForHumans() }}.
                        @else
                            The first message will appear in the log as soon as an app reports an event.
                        @endif
                @endswitch
            </p>
            @unless($canManage)
                <p class="nf-standing">You can read everything here. Only an owner or administrator of {{ $companyName }} can change it.</p>
            @endunless
        </div>

        <div class="nf-act">
            <a class="nf-primary" href="{{ $next['url'] }}" data-primary-action>{{ $next['label'] }}</a>
            <nav aria-label="Dot ecosystem">
                <ul class="nf-back">
                    <li><a href="{{ $infodotUrl }}/home">InfoDot</a></li>
                    <li><a href="{{ $infodotUrl }}/platforms">Platforms</a></li>
                </ul>
            </nav>
        </div>
    </section>

    <nav aria-label="On this page">
        <ul class="nf-jump">
            <li><a href="#log">Log</a></li>
            <li><a href="#batches">Batches</a></li>
            <li><a href="#rules">Rules</a></li>
            <li><a href="#channels">Channels</a></li>
            <li><a href="#templates">Templates</a></li>
            <li><a href="{{ route('connected-apps') }}">Connected apps</a></li>
        </ul>
    </nav>

    <div @class(['nf-band', 'is-solo' => ! $sending && $attention->isEmpty()])>
        @if($sending || $attention->isNotEmpty())
        <section class="nf-section nf-attn" id="attention" aria-labelledby="attention-title">
            <div class="head">
                <h2 class="nf-h2" id="attention-title">Needs attention</h2>
                @if($attention->isNotEmpty())
                    <span class="nf-more">{{ $attention->count() }} {{ Str::plural('thing', $attention->count()) }}</span>
                @endif
            </div>
            @if($attention->isEmpty())
                <p class="nf-clear">Nothing needs attention.</p>
            @else
                <ul class="nf-list">
                    @foreach($attention as $item)
                    <li>
                        <a class="nf-item" href="{{ $item['href'] }}">
                            <span>
                                <span class="nf-item-title">@if($item['code'])<span class="nf-code">{{ $item['code'] }}</span>@endif{{ $item['title'] }}</span>
                                <span class="nf-item-meta">{{ $item['meta'] }}</span>
                            </span>
                            <x-status :kind="$item['mark']" :label="$item['label']" />
                        </a>
                    </li>
                    @endforeach
                </ul>
            @endif
        </section>
        @endif

        <section class="nf-section nf-record" id="record" aria-labelledby="record-title">
            <div class="head"><h2 class="nf-h2" id="record-title">The record</h2></div>
            <dl class="nf-today" aria-label="The record so far">
                @if($sending)
                    <div class="nf-fig"><dt><a href="{{ route('dashboard', ['status' => 'sent']) }}#log">Sent today</a></dt><dd>{{ $sentToday }}</dd></div>
                    <div @class(['nf-fig', 'is-bad' => $failedToday > 0])><dt><a href="{{ route('dashboard', ['status' => 'failed']) }}#log">Failed today</a></dt><dd>{{ $failedToday }}</dd></div>
                    <div @class(['nf-fig', 'is-warn' => $retrying > 0])><dt><a href="{{ route('dashboard', ['status' => 'retrying']) }}#log">Being retried</a></dt><dd>{{ $retrying }}</dd></div>
                    <div class="nf-fig"><dt><a href="{{ route('dashboard', ['status' => 'suppressed']) }}#log">Held back by opt-outs, 30 days</a></dt><dd>{{ $suppressed30d }}</dd></div>
                @endif
                @if($totalChannels > 0)
                    <div class="nf-fig"><dt><a href="#channels">{{ Str::ucfirst($channelWord) }} switched on</a></dt><dd>{{ $activeChannels }}<small> of {{ $totalChannels }}</small></dd></div>
                @else
                    <div class="nf-fig"><dt><a href="#channels">Channels</a></dt><dd>0</dd></div>
                @endif
                <div class="nf-fig"><dt><a href="#rules">Rules switched on</a></dt><dd>{{ $activeRules }}</dd></div>
            </dl>
            <p class="nf-more nf-record-note">Sent means a channel accepted the message. Dot.Notify receives no opens, clicks or bounces, so it shows none.</p>
        </section>
    </div>

    {{-- Notification log (master) and the selected message (detail) --}}
    <section class="nf-section" id="log" aria-label="Notification log and selected message">
        <livewire:notify.notification-center :filter-status="$logStatus" />
    </section>

    {{-- Rules and batches | channels and templates --}}
    <div class="nf-lower">
        <div>
            <section class="nf-section" id="rules">
                <livewire:notify.rule-manager />
            </section>

            <section class="nf-section" id="batches" aria-labelledby="batches-title">
                <div class="card-head">
                    <h2 class="nf-h2" id="batches-title">Recent batches</h2>
                </div>
                @if($recentBatches->isEmpty())
                    <p class="nf-empty">No bulk sends yet. Batches you create will show up here with how many were sent.</p>
                @else
                    <div class="nf-rows">
                        @foreach($recentBatches as $batch)
                        <div class="dot-row">
                            <div class="dot-row-main">
                                <div class="row-break nf-row-title">{{ $batch->name }}</div>
                                <div class="nf-row-meta">{{ $batch->sent_count }} of {{ $batch->total_recipients }} sent ({{ $batch->deliveryRate() }}%), started {{ $batch->created_at->diffForHumans() }}</div>
                            </div>
                            <x-status :kind="$batch->status === 'completed' ? 'sent' : ($batch->status === 'failed' ? 'failed' : 'waiting')" :label="ucfirst($batch->status)" />
                        </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        <div>
            <section class="nf-section" id="channels">
                <livewire:notify.channel-manager :show-form="$openChannelForm" />
            </section>
            <section class="nf-section" id="templates">
                <livewire:notify.template-editor />
            </section>
        </div>
    </div>

    <div class="nf-foot">
        <span><b>{{ $sentToday }}</b> sent today, <b>{{ $failedToday }}</b> failed</span>
        <span><b>{{ $activeChannels }}</b> of {{ $totalChannels }} {{ $channelWord }} on</span>
        <span><b>{{ $activeRules }}</b> {{ Str::plural('rule', $activeRules) }} on</span>
        <p>Dot.Notify records that a channel accepted a message. It receives no opens, clicks, bounces or inbox confirmations, so it shows none.</p>
    </div>

</div>

</x-app-layout>
