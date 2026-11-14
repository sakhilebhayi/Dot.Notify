{{-- R-50: the company does not hold Dot.Notify. A slip from The Message Pad: the state that applies is the one box
     ticked, and there is ONE action (data-primary-action). Rendered by App\Support\EntitlementRefusal with HTTP 403.
     It shows nothing from the desk: no channels, rules, templates or messages. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · Dot.Notify</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <script>
        (function () {
            var stored = null;
            try { stored = localStorage.getItem('dot-theme'); } catch (e) {}
            if (stored === 'dark' || (!stored && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    @vite(['resources/css/notify.css'])
</head>
<body class="nf-gate-page">
    <main class="nf-gate">
        <header class="nf-gate-top">
            <img class="nf-gate-logo nf-logo-light" src="{{ asset('images/logo.png') }}" alt="Dot.Notify" width="1181" height="886">
            <img class="nf-gate-logo nf-logo-dark" src="{{ asset('images/logo-light.png') }}" alt="Dot.Notify" width="1181" height="886">
            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="nf-linkbtn">Sign out</button>
                </form>
            @endauth
        </header>

        <section class="nf-slip nf-gate-slip" aria-labelledby="gate-title">
            <h1 class="nf-gate-title" id="gate-title">{{ $title }}</h1>
            <p class="nf-gate-body">{{ $body }}</p>
            @if ($state !== null)
                <ul class="nf-ticks nf-gate-ticks" aria-label="Where it stands">
                    @foreach ($states as $key => $label)
                        <li class="nf-tick {{ $key === $state ? 'is-on' : '' }}">
                            <span class="nf-box" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
                            <span>{{ $label }}@if ($key === $state)<span class="sr-only"> (this applies)</span>@endif</span>
                        </li>
                    @endforeach
                </ul>
            @endif
            <a class="nf-primary" href="{{ $platformsUrl }}" data-primary-action>Open Platforms in InfoDot</a>
        </section>

        @auth
            @if ($otherCompanies->isNotEmpty())
                <section class="nf-gate-others" aria-labelledby="gate-others">
                    <h2 class="nf-h2" id="gate-others">Your other {{ $otherCompanies->count() === 1 ? 'company' : 'companies' }} here</h2>
                    <div class="nf-rows">
                        @foreach ($otherCompanies as $other)
                            <div class="dot-row">
                                <div class="dot-row-main nf-row-title">{{ $other->name }}</div>
                                <form method="POST" action="{{ route('current-team.update') }}" class="dot-row-actions">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="team_id" value="{{ $other->id }}">
                                    <button type="submit" class="dot-btn dot-btn-ghost" aria-label="Switch to {{ $other->name }}">Switch</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        @endauth
    </main>
</body>
</html>
