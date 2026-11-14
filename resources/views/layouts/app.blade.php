<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dot-roboto">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
    <meta name="user-id" content="{{ auth()->id() }}">
    @endauth
    <title>@isset($header){{ trim(strip_tags((string) $header)) }} · @endisset{{ 'Dot.Notify' }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    {{-- Light or dark: the person's stored choice, else the system's, set before first paint so nothing flashes. A plain external
         file (no inline script), loaded in order. --}}
    <script src="{{ asset('js/notify-shell.js') }}"></script>
    <link rel="preload" href="{{ asset('dot-ui/fonts/roboto/roboto-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    {{-- Roboto and the shared type scale, from this site's own public/dot-ui (self-hosted: no CDN, no external font). --}}
    <link rel="stylesheet" href="{{ asset('dot-ui/css/dot-roboto.css') }}">
    <link rel="stylesheet" href="{{ asset('dot-ui/css/dot-type.css') }}">
    {{-- The Message Pad (DESIGN.md): tokens, the shell and the home page. --}}
    @vite(['resources/css/notify.css'])
    {{-- Shared Dot UI (header, search, platform switcher), served from this site's own public/dot-ui: no CDN. Loaded after the
         app stylesheet's tokens so the host mapping block in notify.css decides the surface colours. --}}
    <link rel="stylesheet" href="{{ asset('dot-ui/css/dot-tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('dot-ui/css/dot-search.css') }}">
    <link rel="stylesheet" href="{{ asset('dot-ui/css/dot-switcher.css') }}">
    <link rel="stylesheet" href="{{ asset('dot-ui/css/dot-header.css') }}">
    {{-- Tailwind, built (resources/css/jetstream.css), styles the Jetstream account pages (profile, team) that render inside this
         shell. It replaces the Play CDN script: self-hosted, no runtime compile, nothing for a CSP to block. It follows the
         notify stylesheet, as the CDN's injected <style> did, and has no preflight, so the pad's own base is untouched. --}}
    @vite(['resources/css/jetstream.css'])
    @livewireStyles
    {{-- No stand-alone Alpine here: Livewire 3 bundles and starts its own (@livewireScripts). A second copy
         replaces window.Alpine and breaks every Livewire re-render ('Alpine.cloneNode is not a function'). --}}
</head>
@php
    $user = Auth::user();
    $team = $user?->currentTeam;
    $teamName = $team?->name ?? 'Personal';
    $account = $user ? [
        'name' => (string) $user->name,
        'email' => (string) $user->email,
        'team' => $teamName,
        'links' => array_values(array_filter([
            ['label' => 'Profile & Settings', 'href' => route('profile.show'), 'current' => request()->routeIs('profile.show')],
            $team ? ['label' => 'Team settings', 'href' => route('teams.show', $team->id), 'current' => request()->routeIs('teams.show')] : null,
            Gate::forUser($user)->allows('create', Laravel\Jetstream\Jetstream::newTeamModel()) ? ['label' => 'Create team', 'href' => route('teams.create'), 'current' => request()->routeIs('teams.create')] : null,
        ])),
        'signOut' => ['action' => route('logout')],
        'theme' => true,
    ] : null;
@endphp
<body class="shell-body">
    <x-dot.header
        :brand-href="$user ? route('dashboard') : url('/')"
        brand-label="Dot.Notify home"
        :nav="[]"
        :account="$account"
        :context="$user ? $teamName : null"
        skip="#main"
        :drawer-target="$user ? 'shell-drawer' : null"
        drawer-title="Menu"
        class="nf-header {{ $user ? '' : 'nf-header--guest' }}">
        <x-slot:brand>
            <img class="nf-brand-mark" src="{{ asset('images/mark-light.png') }}" alt="" width="28" height="28">
            <span class="dh-brand__name">Dot.Notify</span>
        </x-slot:brand>
        @auth
        <x-slot:search>
            <livewire:notify.header-search />
        </x-slot:search>
        <x-slot:tools>
            <x-dot.switcher platform="notify" :company="$team?->infodot_team_uuid" :company-name="$team?->name" logos="/dot-ui/img/platforms" :compact="true" />
            <livewire:notify.notification-bell />
        </x-slot:tools>
        @endauth
    </x-dot.header>

    @auth
    <div class="shell">
    <aside class="sidebar" id="shell-drawer" aria-label="Sidebar" tabindex="-1">
        <div class="sidebar-head">
            <span class="sidebar-head-title">Menu</span>
            <button type="button" class="shell-close" data-dh-close aria-label="Close menu">
                <x-icon name="close" />
            </button>
        </div>

        <nav class="sidebar-nav" aria-label="Main">
            <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>
                <x-icon name="home" class="nav-icon" />
                Dashboard
            </a>
            <a href="{{ route('connected-apps') }}" class="nav-item {{ request()->routeIs('connected-apps') ? 'active' : '' }}" @if(request()->routeIs('connected-apps')) aria-current="page" @endif>
                <x-icon name="apps" class="nav-icon" />
                Connected apps
            </a>

            <div class="nav-section-label">On the dashboard</div>
            <a href="{{ route('dashboard') }}#log" class="nav-item"><x-icon name="email" class="nav-icon" />Notification log</a>
            <a href="{{ route('dashboard') }}#rules" class="nav-item"><x-icon name="webhook" class="nav-icon" />Rules</a>
            <a href="{{ route('dashboard') }}#channels" class="nav-item"><x-icon name="push" class="nav-icon" />Channels</a>
            <a href="{{ route('dashboard') }}#templates" class="nav-item"><x-icon name="draft" class="nav-icon" />Templates</a>

            @if($team && $user->allTeams()->count() > 1)
            <div class="nav-section-label">Switch team</div>
            @foreach($user->allTeams() as $switchTeam)
            <form method="POST" action="{{ route('current-team.update') }}">
                @method('PUT')
                @csrf
                <input type="hidden" name="team_id" value="{{ $switchTeam->id }}">
                <button type="submit" class="nav-item" @if($user->isCurrentTeam($switchTeam)) aria-current="true" @endif>
                    @if($user->isCurrentTeam($switchTeam))<x-icon name="check" class="nav-team-mark" />@else<span class="nav-team-mark" aria-hidden="true"></span>@endif
                    <span class="nav-label">{{ $switchTeam->name }}</span>
                </button>
            </form>
            @endforeach
            @endif

            {{-- The account, only inside the phone menu: from 860px the header's account menu holds all of it. --}}
            <div class="sidebar-account">
                <div class="sidebar-divider"></div>
                <div class="user-row">
                    <div class="user-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                    <div class="user-meta">
                        <div class="user-name">{{ $user->name }}</div>
                        <div class="user-team">{{ $teamName }}</div>
                    </div>
                </div>
                <a href="{{ route('profile.show') }}" class="nav-item {{ request()->routeIs('profile.show') ? 'active' : '' }}" @if(request()->routeIs('profile.show')) aria-current="page" @endif>
                    <x-icon name="user" class="nav-icon" />
                    Profile &amp; Settings
                </a>
                @if($team)
                <a href="{{ route('teams.show', $team->id) }}" class="nav-item {{ request()->routeIs('teams.show') ? 'active' : '' }}" @if(request()->routeIs('teams.show')) aria-current="page" @endif>
                    <x-icon name="users" class="nav-icon" />
                    Team settings
                </a>
                @endif
                @can('create', Laravel\Jetstream\Jetstream::newTeamModel())
                <a href="{{ route('teams.create') }}" class="nav-item {{ request()->routeIs('teams.create') ? 'active' : '' }}" @if(request()->routeIs('teams.create')) aria-current="page" @endif>
                    <x-icon name="user-plus" class="nav-icon" />
                    Create team
                </a>
                @endcan
                <div class="sidebar-theme" role="group" aria-label="Theme">
                    <button type="button" class="sidebar-theme-btn" data-dh-theme-set="light" aria-pressed="false">Light</button>
                    <button type="button" class="sidebar-theme-btn" data-dh-theme-set="dark" aria-pressed="false">Dark</button>
                    <button type="button" class="sidebar-theme-btn" data-dh-theme-set="system" aria-pressed="false">System</button>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="nav-item">
                        <x-icon name="logout" class="nav-icon" />
                        Log out
                    </button>
                </form>
            </div>
        </nav>
    </aside>

    <div class="content-wrap" id="main" tabindex="-1">
        <main>{{ $slot }}</main>
    </div>
    </div>
    @else
    <div class="content-wrap content-wrap--guest" id="main" tabindex="-1">
        <main>{{ $slot }}</main>
    </div>
    @endauth

    @stack('modals')
    @livewireScripts
    <script src="{{ asset('dot-ui/js/dot-search.js') }}" defer></script>
    <script src="{{ asset('dot-ui/js/dot-switcher.js') }}" defer></script>
    <script src="{{ asset('dot-ui/js/dot-nav.js') }}" defer></script>
</body>
</html>
