@php
    $r = $this->results;
@endphp
<div>
    <x-dot.search
        name="q"
        :action="route('search')"
        wire="query"
        hint="Ctrl K"
        label="Search Dot.Notify"
        placeholder="Search messages, rules, channels, templates"
        :escape-clears="true"
        loading-text="Looking through this company's records..."
        :root-attributes="['wire:loading.attr' => 'data-dsr-loading', 'wire:target' => 'query']">
        <x-slot:results>
            @if($r['ready'] && $r['groups'] !== [])
                <div class="dsr-quick">
                    @foreach($r['groups'] as $group)
                        <div class="dsr-head">{{ $group['label'] }}</div>
                        @foreach($group['items'] as $item)
                            <a class="dsr-item" href="{{ $item['href'] }}" role="option">
                                <span class="dsr-label">
                                    {{ $item['title'] }}
                                    @if($item['meta'] !== '')<span class="nf-hit-meta">{{ $item['meta'] }}</span>@endif
                                    @if($item['code'])<span class="nf-hit-meta nf-code">{{ $item['code'] }}</span>@endif
                                </span>
                                <span class="dsr-tag">{{ $item['tag'] }}</span>
                            </a>
                        @endforeach
                    @endforeach
                    <a class="dsr-item dsr-item--action" href="{{ route('search', ['q' => $r['query']]) }}" role="option">See every result for &ldquo;{{ $r['query'] }}&rdquo;</a>
                </div>
            @elseif($r['ready'])
                <div class="dsr-quick">
                    <p class="dsr-foot">Nothing in this company matches &ldquo;{{ $r['query'] }}&rdquo;. Try a recipient, a subject, an event or a name.</p>
                </div>
            @else
                <div class="dsr-quick">
                    <div class="dsr-head">Search this company</div>
                    <p class="dsr-foot">Type at least {{ \App\Support\HeaderSearch::MIN_LENGTH }} letters of a recipient, a subject, an event name, a rule, a channel or a template.</p>
                    <div class="dsr-head">Go to</div>
                    <div class="dsr-chips">
                        <a class="dsr-chip" role="option" href="{{ route('dashboard') }}#log">Notification log</a>
                        <a class="dsr-chip" role="option" href="{{ route('dashboard') }}#rules">Rules</a>
                        <a class="dsr-chip" role="option" href="{{ route('dashboard') }}#channels">Channels</a>
                        <a class="dsr-chip" role="option" href="{{ route('dashboard') }}#templates">Templates</a>
                        <a class="dsr-chip" role="option" href="{{ route('connected-apps') }}">Connected apps</a>
                    </div>
                </div>
            @endif
        </x-slot:results>
    </x-dot.search>
</div>
