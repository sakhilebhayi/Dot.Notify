<x-app-layout>
<x-slot name="header">Search</x-slot>

@php
    $minLength = \App\Support\HeaderSearch::MIN_LENGTH;
@endphp

<div class="page">
    <section aria-labelledby="search-title">
        <h1 class="nf-title" id="search-title">
            @if($results['ready'])
                {{ $results['total'] === 0 ? 'Nothing found' : $results['total'].' '.Str::plural('result', $results['total']) }} for &ldquo;{{ $results['query'] }}&rdquo;
            @else
                Search {{ Auth::user()->currentTeam->name }}
            @endif
        </h1>
        <p class="nf-say">
            @if($results['ready'] && $results['total'] === 0)
                Nothing in {{ Auth::user()->currentTeam->name }} matches. Try a recipient, a subject, an event name, or the name of a rule, channel or template. Message bodies are not searched.
            @elseif($results['ready'])
                Only {{ Auth::user()->currentTeam->name }}&rsquo;s own records are searched: recipient, subject, event and status of messages, and the names of rules, channels, templates and batches. Message bodies are not searched.
            @else
                Type at least {{ $minLength }} letters in the search box above.
            @endif
        </p>
    </section>

    @foreach($results['groups'] as $group)
        <section class="nf-section" aria-labelledby="hits-{{ $group['key'] }}">
            <div class="head">
                <h2 class="nf-h2" id="hits-{{ $group['key'] }}">{{ $group['label'] }}</h2>
                @if($group['more'] > 0)<span class="nf-more">Showing {{ count($group['items']) }} of {{ count($group['items']) + $group['more'] }}</span>@endif
            </div>
            <ul class="nf-list">
                @foreach($group['items'] as $item)
                <li>
                    <a class="nf-item" href="{{ $item['href'] }}">
                        <span>
                            <span class="nf-item-title">{{ $item['title'] }}</span>
                            @if($item['meta'] !== '')<span class="nf-item-meta">{{ $item['meta'] }}</span>@endif
                            @if($item['code'])<span class="nf-item-meta nf-code">{{ $item['code'] }}</span>@endif
                        </span>
                        <span class="nf-soft">{{ $item['tag'] }}</span>
                    </a>
                </li>
                @endforeach
            </ul>
        </section>
    @endforeach
</div>
</x-app-layout>
