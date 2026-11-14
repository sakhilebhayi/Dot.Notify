<x-app-layout>

@php
    // How a log row's status is ticked on the slip. Old rows may carry statuses nothing writes any more;
    // they fall in with the state they amount to (NotifyLog::wasSent / hasFailed).
    $slipState = match (true) {
        $latestLog === null => null,
        $latestLog->wasSent() => 'sent',
        $latestLog->hasFailed() => 'failed',
        in_array($latestLog->status, ['queued', 'sending', 'retrying', 'suppressed'], true) => $latestLog->status,
        default => null,
    };
    $slipStates = ['queued' => 'Queued', 'sending' => 'Sending', 'retrying' => 'Retrying', 'sent' => 'Sent', 'failed' => 'Failed', 'suppressed' => 'Suppressed'];
    $channelWord = Str::plural('channel', $totalChannels);
@endphp

<div class="page">

    <header class="nf-top">
        <img class="nf-logo nf-logo-light" src="{{ asset('images/logo.png') }}" alt="Dot.Notify" width="1181" height="886">
        <img class="nf-logo nf-logo-dark" src="{{ asset('images/logo-light.png') }}" alt="Dot.Notify" width="1181" height="886">
        <nav aria-label="Dot ecosystem">
            <ul class="nf-back">
                <li><a href="{{ $infodotUrl }}/home">InfoDot</a></li>
                <li><a href="{{ $infodotUrl }}/platforms">Platforms</a></li>
            </ul>
        </nav>
    </header>

    <div class="nf-hero">
        <div class="nf-intro">
            <h1 class="nf-title">Make sure the right person is told.</h1>
            <p class="nf-lede">
                Welcome, {{ $firstName }}. Dot.Notify takes what happens in the other Dot apps {{ $companyName }} uses and tells
                the person it concerns, through the channels and rules {{ $canManage ? 'you set' : 'your administrators set' }}.
                Every message it hands over is on record below.
            </p>

            <div class="nf-next">
                <p class="nf-finding">
                    @switch($stage)
                        @case('no_channel')
                            {{ $companyName }} has no channel yet, so nothing can be sent.
                            <span class="nf-finding-more">
                                @if($canManage)
                                    Start with an email channel: the events your Dot apps send go out by email.
                                @else
                                    An owner or administrator adds channels. You can see what each app is able to send.
                                @endif
                            </span>
                            @break
                        @case('channels_off')
                            {{ $totalChannels === 1 ? 'Your one channel is' : "All {$totalChannels} channels are" }} switched off, so nothing can be sent.
                            <span class="nf-finding-more">
                                @if($canManage)
                                    Switch one back on under Channels.
                                @else
                                    An owner or administrator can switch one back on.
                                @endif
                            </span>
                            @break
                        @case('no_rule')
                            No rule is switched on, so nothing is sent yet.
                            <span class="nf-finding-more">
                                @if($canManage)
                                    You have {{ $activeChannels }} working {{ Str::plural('channel', $activeChannels) }}. Choose the events people should be told about.
                                @else
                                    An owner or administrator chooses which events are sent. You can see the list.
                                @endif
                            </span>
                            @break
                        @default
                            @if($failedToday > 0)
                                {{ $failedToday }} {{ Str::plural('message', $failedToday) }} failed today.
                                <span class="nf-finding-more">{{ $sentToday }} sent today.</span>
                            @elseif($sentToday > 0)
                                {{ $sentToday }} {{ Str::plural('message', $sentToday) }} sent today, none failed.
                            @elseif($lastSentAt)
                                Nothing has been sent today.
                                <span class="nf-finding-more">The last message went out {{ \Illuminate\Support\Carbon::parse($lastSentAt)->diffForHumans() }}.</span>
                            @else
                                Your channels and rules are ready, and nothing has been sent yet.
                                <span class="nf-finding-more">The first message will appear in the log as soon as an app reports an event.</span>
                            @endif
                    @endswitch
                </p>

                <a class="nf-primary" href="{{ $next['url'] }}" data-primary-action>{{ $next['label'] }}</a>

                @unless($canManage)
                    <p class="nf-standing">You can read everything here. Only an owner or administrator of {{ $companyName }} can change it.</p>
                @endunless
            </div>
        </div>

        <section class="nf-slip" aria-labelledby="slip-title">
            <div class="nf-slip-head">
                <h2 class="nf-slip-title" id="slip-title">Latest message</h2>
                @if($latestLog)
                    <time class="nf-slip-time" datetime="{{ $latestLog->created_at->toIso8601String() }}">{{ $latestLog->created_at->format('j M Y, H:i') }}</time>
                @endif
            </div>

            @if($latestLog)
                <dl class="nf-fields">
                    <div class="nf-field"><dt>To</dt><dd>{{ $latestLog->recipient }}</dd></div>
                    <div class="nf-field"><dt>About</dt><dd @class(['is-blank' => ! $latestLog->subject])>{{ $latestLog->subject ?? 'No subject' }}</dd></div>
                    <div class="nf-field"><dt>Event</dt><dd @class(['is-blank' => ! $latestLog->event])>@if($latestLog->event)<span class="nf-code">{{ $latestLog->event }}</span>@else Not from a catalogued event @endif</dd></div>
                    <div class="nf-field"><dt>Through</dt><dd @class(['is-blank' => ! $latestLog->channel])>{{ $latestLog->channel?->name ?? 'No channel recorded' }}</dd></div>
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
                        @default Recorded as "{{ $latestLog->status }}".
                    @endswitch
                </p>
            @else
                <p class="nf-slip-empty">Nothing has been handed over for {{ $companyName }} yet. The first message will be shown here, with what became of it.</p>
            @endif
        </section>
    </div>

    <dl class="nf-today" aria-label="The record so far">
        <div class="nf-fig"><dt>sent today</dt><dd>{{ $sentToday }}</dd></div>
        <div @class(['nf-fig', 'is-bad' => $failedToday > 0])><dt>failed today</dt><dd>{{ $failedToday }}</dd></div>
        <div @class(['nf-fig', 'is-warn' => $retrying > 0])><dt>being retried</dt><dd>{{ $retrying }}</dd></div>
        <div class="nf-fig"><dt>held back by opt-outs in 30 days</dt><dd>{{ $suppressed30d }}</dd></div>
        @if($totalChannels > 0)
            <div class="nf-fig"><dt>{{ $channelWord }} switched on</dt><dd>{{ $activeChannels }}<small> of {{ $totalChannels }}</small></dd></div>
        @else
            <div class="nf-fig"><dt>channels</dt><dd>0</dd></div>
        @endif
    </dl>

    <nav aria-label="On this page">
        <ul class="nf-jump">
            <li class="nf-jump-label">On this page</li>
            <li><a href="#log">Notification log</a></li>
            <li><a href="#batches">Recent batches</a></li>
            <li><a href="#channels">Channels</a></li>
            <li><a href="#templates">Templates</a></li>
            <li><a href="#rules">Routing rules</a></li>
            <li><a href="{{ route('connected-apps') }}">Connected apps</a></li>
        </ul>
    </nav>

    {{-- Notification Log --}}
    <section class="nf-section" id="log">
        <livewire:notify.notification-center :filter-status="$logStatus" />
    </section>

    {{-- Recent Batches --}}
    <section class="nf-section" id="batches" aria-labelledby="batches-title">
        <div class="card-head">
            <h2 class="nf-h2" id="batches-title">Recent Batches</h2>
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

    {{-- Channels + Templates --}}
    <div class="two-col">
        <section class="nf-section" id="channels">
            <livewire:notify.channel-manager :show-form="$openChannelForm" />
        </section>
        <section class="nf-section" id="templates">
            <livewire:notify.template-editor />
        </section>
    </div>

    {{-- Routing Rules --}}
    <section class="nf-section" id="rules">
        <livewire:notify.rule-manager />
    </section>

    <p class="nf-foot">
        Dot.Notify records that a channel accepted a message. It receives no opens, clicks, bounces or inbox confirmations, so it shows none.
    </p>

</div>

</x-app-layout>
