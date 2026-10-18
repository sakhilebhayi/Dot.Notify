<div>
    <style>
        .ca-head { font-family:'Syne',sans-serif; font-size:1.5rem; font-weight:700; color:#f4f4f5; margin:0 0 .35rem; letter-spacing:-0.01em; }
        .ca-lede { font-size:13px; color:#a1a1aa; margin:0; max-width:46rem; line-height:1.55; }
        .ca-notice { margin:1.25rem 0 0; padding:.6rem .85rem; font-size:13px; color:#f4f4f5; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); border-radius:8px; }
        .ca-notice:empty { display:none; }
        .ca-app { margin-top:1.25rem; overflow:hidden; }
        .ca-app-head { display:flex; align-items:baseline; justify-content:space-between; gap:1rem; padding:1rem 1.25rem; border-bottom:1px solid rgba(255,255,255,0.07); }
        .ca-app-name { font-family:'Syne',sans-serif; font-size:.95rem; font-weight:700; color:#f4f4f5; margin:0; }
        .ca-count { font-family:'JetBrains Mono',monospace; font-size:12px; color:#a1a1aa; white-space:nowrap; }
        .ca-offline { margin:0; padding:.7rem 1.25rem; font-size:12px; color:#f59e0b; border-bottom:1px solid rgba(255,255,255,0.07); }
        .ca-row { display:grid; grid-template-columns:minmax(0,1fr) 15rem 11rem; gap:1rem 1.5rem; align-items:start; padding:1rem 1.25rem; border-top:1px solid rgba(255,255,255,0.07); }
        .ca-app-head + .ca-row, .ca-offline + .ca-row { border-top:0; }
        .ca-label { font-size:13px; font-weight:600; color:#f4f4f5; }
        .ca-event { font-family:'JetBrains Mono',monospace; font-size:11px; color:#a1a1aa; margin-left:.5rem; }
        .ca-desc { font-size:12px; color:#a1a1aa; margin:.2rem 0 0; line-height:1.5; }
        .ca-meta { font-size:12px; color:#a1a1aa; line-height:1.5; }
        .ca-meta strong { color:#d4d4d8; font-weight:600; }
        .ca-switchcell { display:flex; flex-direction:column; align-items:flex-start; gap:.5rem; }
        .ca-switchline { display:flex; align-items:center; gap:.6rem; font-size:12px; font-weight:600; color:#d4d4d8; }
        .ca-switch { position:relative; width:38px; height:22px; padding:0; border-radius:100px; border:1px solid rgba(255,255,255,0.18); background:rgba(255,255,255,0.08); cursor:pointer; transition:background .14s,border-color .14s; flex-shrink:0; }
        .ca-switch::after { content:''; position:absolute; top:2px; left:2px; width:16px; height:16px; border-radius:50%; background:#d4d4d8; transition:transform .14s,background .14s; }
        .ca-switch[aria-checked="true"] { background:#fb923c; border-color:#fb923c; }
        .ca-switch[aria-checked="true"]::after { transform:translateX(16px); background:#09090b; }
        .ca-switch:focus-visible, .ca-link:focus-visible, .ca-details summary:focus-visible { outline:2px solid #fb923c; outline-offset:2px; }
        .ca-switch[disabled] { opacity:.55; cursor:progress; }
        .ca-flag { font-size:12px; color:#f59e0b; line-height:1.45; margin:0; }
        .ca-link { background:none; border:0; padding:0; font:inherit; font-size:12px; font-weight:600; color:#fb923c; cursor:pointer; text-decoration:underline; text-underline-offset:2px; }
        .ca-state { font-size:12px; font-weight:600; color:#d4d4d8; }
        .ca-state-on { color:#22c55e; }
        .ca-details { grid-column:1 / -1; }
        .ca-details summary { font-size:12px; color:#a1a1aa; cursor:pointer; width:fit-content; }
        .ca-details summary:hover { color:#f4f4f5; }
        .ca-details-body { margin-top:.6rem; display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:1rem 1.5rem; font-size:12px; color:#a1a1aa; }
        .ca-pre { margin:.25rem 0 0; padding:.6rem .75rem; white-space:pre-wrap; font-family:'JetBrains Mono',monospace; font-size:11px; line-height:1.55; color:#d4d4d8; background:rgba(255,255,255,0.04); border-radius:6px; }
        .ca-h { font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:#a1a1aa; }
        .ca-keys { display:flex; flex-wrap:wrap; gap:.3rem; margin:.35rem 0 0; padding:0; list-style:none; }
        .ca-keys code { font-family:'JetBrains Mono',monospace; font-size:11px; color:#d4d4d8; background:rgba(255,255,255,0.06); padding:1px 6px; border-radius:4px; }
        @media (max-width: 960px) {
            .ca-row { grid-template-columns:minmax(0,1fr); }
            .ca-details-body { grid-template-columns:minmax(0,1fr); }
        }
    </style>

    <h1 class="ca-head">Connected apps</h1>
    <p class="ca-lede">
        Dot apps tell Dot.Notify when something happens to someone. Nothing is sent for an event until you switch it on here.
        Switching one on gives your company its own copy of the default email and a rule that sends it through your first active email channel.
        Both show up on the dashboard under Templates and Routing Rules. You can point a rule at a different template or channel there, and switching an event off only turns the rule off: nothing is deleted.
    </p>

    <div class="ca-notice" role="status" aria-live="polite">{{ $notice }}</div>

    @foreach($this->apps as $app)
        <section class="dot-card ca-app" aria-labelledby="app-{{ $app['key'] }}">
            <div class="ca-app-head">
                <h2 class="ca-app-name" id="app-{{ $app['key'] }}">{{ $app['name'] }}</h2>
                <span class="ca-count">{{ $app['on'] }} of {{ $app['total'] }} on</span>
            </div>

            @unless($app['connected'])
                <p class="ca-offline">This app is not connected to Dot.Notify yet, so it cannot send events. Ask your administrator to finish setting it up.</p>
            @endunless

            @foreach($app['events'] as $event => $item)
                <div class="ca-row">
                    <div>
                        <div>
                            <span class="ca-label">{{ $item['label'] }}</span><span class="ca-event">{{ $event }}</span>
                        </div>
                        <p class="ca-desc">{{ $item['description'] }}</p>
                    </div>

                    <div class="ca-meta">
                        <div><strong>Sent to</strong> {{ $item['recipient'] === 'approvers' ? 'the people who approve it' : 'the person it is about' }}, by {{ $item['channel'] }}</div>
                        <div>
                            <strong>Last received</strong>
                            @if($item['last'])
                                <time datetime="{{ $item['last']['at']->toIso8601String() }}" title="{{ $item['last']['at']->format('j M Y H:i') }}">{{ $item['last']['at']->diffForHumans() }}</time>.
                                {{ match($item['last']['status']) {
                                    'routed' => 'Queued for delivery',
                                    'no_rule' => 'Received, but no rule is on so nothing was sent',
                                    'ignored' => 'Held back by the rule\'s hourly limit',
                                    'failed' => 'Received, but it could not be sent: the channel or template is missing or switched off',
                                    default => 'Received',
                                } }}
                            @else
                                Never received
                            @endif
                        </div>
                    </div>

                    <div class="ca-switchcell">
                        @can('create', [\App\Models\NotifyRule::class, $team])
                            <div class="ca-switchline">
                                <button type="button" role="switch" class="ca-switch"
                                    aria-checked="{{ $item['state'] === 'off' ? 'false' : 'true' }}"
                                    aria-label="{{ $item['label'] }} by email"
                                    wire:click="toggle('{{ $event }}')" wire:loading.attr="disabled" wire:target="toggle"></button>
                                <span>{{ $item['state'] === 'off' ? 'Off' : 'On' }}</span>
                            </div>
                        @else
                            <span class="ca-state {{ $item['state'] === 'on' ? 'ca-state-on' : '' }}">{{ $item['state'] === 'off' ? 'Off' : 'On' }}</span>
                        @endcan

                        @if($item['state'] === 'no_channel')
                            <p class="ca-flag">
                                No email channel yet: events arrive, but nothing is sent.
                                @can('create', [\App\Models\NotifyRule::class, $team])
                                    Add an email channel on the dashboard, then
                                    <button type="button" class="ca-link" wire:click="attachChannel('{{ $event }}')">attach it</button>.
                                @endcan
                            </p>
                        @elseif($item['state'] === 'blocked')
                            <p class="ca-flag">Its channel or template is switched off or missing, so nothing is sent.</p>
                        @endif
                    </div>

                    <details class="ca-details">
                        <summary>Default message and data</summary>
                        <div class="ca-details-body">
                            <div>
                                <div class="ca-h">Subject</div>
                                <pre class="ca-pre">{{ $item['template']['subject'] }}</pre>
                                <div class="ca-h" style="margin-top:.6rem;">Body</div>
                                <pre class="ca-pre">{{ $item['template']['body'] }}</pre>
                            </div>
                            <div>
                                <div class="ca-h">Data the app can send</div>
                                <ul class="ca-keys">
                                    @foreach($item['data'] as $key)
                                        <li><code>{{ $key }}</code></li>
                                    @endforeach
                                </ul>
                                <p class="ca-desc">Anything else the app sends is dropped before it is stored or used.</p>
                            </div>
                        </div>
                    </details>
                </div>
            @endforeach
        </section>
    @endforeach
</div>
