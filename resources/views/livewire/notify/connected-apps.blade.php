<div>
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
                                <div class="ca-h ca-h-gap">Body</div>
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
