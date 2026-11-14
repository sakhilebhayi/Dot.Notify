<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
    <meta name="user-id" content="{{ auth()->id() }}">
    @endauth
    <title>Dot.Notify</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    {{-- Tailwind's CDN build styles only the Jetstream account pages (profile, team) that render inside this shell.
         The shell itself, the home page and Connected apps are styled by resources/css/notify.css. --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class', corePlugins: { preflight: false } }</script>
    <script>
        // Light or dark: the person's stored choice, else the system's. Set before first paint so nothing flashes.
        (function () {
            var stored = null;
            try { stored = localStorage.getItem('dot-theme'); } catch (e) {}
            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (stored === 'dark' || (!stored && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    {{-- The Message Pad (DESIGN.md): tokens, self-hosted typefaces, the shell and the home page. --}}
    @vite(['resources/css/notify.css'])
    @livewireStyles
    {{-- No stand-alone Alpine here: Livewire 3 bundles and starts its own (@livewireScripts). A second copy
         replaces window.Alpine and breaks every Livewire re-render ('Alpine.cloneNode is not a function'). --}}
</head>
<body>
    <div data-shell-behind><x-banner /></div>

    <div id="shell-backdrop" class="shell-backdrop" aria-hidden="true"></div>

    <aside class="sidebar" id="shell-drawer" aria-label="Sidebar" tabindex="-1">
        <div class="sidebar-brand">
            <img class="brand-mark" src="{{ asset('images/mark-light.png') }}" alt="" width="36" height="36">
            <div class="brand-name">Dot.Notify</div>
            <button type="button" id="shell-close" class="shell-close" aria-label="Close menu">
                <x-icon name="close" />
            </button>
        </div>

        <div class="sidebar-divider"></div>

        <nav class="sidebar-nav" aria-label="Main">
            <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>
                <x-icon name="home" class="nav-icon" />
                Dashboard
            </a>
            <a href="{{ route('connected-apps') }}" class="nav-item {{ request()->routeIs('connected-apps') ? 'active' : '' }}" @if(request()->routeIs('connected-apps')) aria-current="page" @endif>
                <x-icon name="apps" class="nav-icon" />
                Connected apps
            </a>
            <div class="sidebar-divider"></div>
            <a href="{{ route('profile.show') }}" class="nav-item {{ request()->routeIs('profile.show') ? 'active' : '' }}" @if(request()->routeIs('profile.show')) aria-current="page" @endif>
                <x-icon name="user" class="nav-icon" />
                Profile &amp; Settings
            </a>

            @auth
            @if(Auth::user()->currentTeam)
            <div class="nav-section-label">Team</div>
            <a href="{{ route('teams.show', Auth::user()->currentTeam->id) }}" class="nav-item {{ request()->routeIs('teams.show') ? 'active' : '' }}" @if(request()->routeIs('teams.show')) aria-current="page" @endif>
                <x-icon name="users" class="nav-icon" />
                Team settings
            </a>
            @can('create', Laravel\Jetstream\Jetstream::newTeamModel())
            <a href="{{ route('teams.create') }}" class="nav-item {{ request()->routeIs('teams.create') ? 'active' : '' }}" @if(request()->routeIs('teams.create')) aria-current="page" @endif>
                <x-icon name="user-plus" class="nav-icon" />
                Create team
            </a>
            @endcan
            @if(Auth::user()->allTeams()->count() > 1)
            <div class="nav-section-label">Switch team</div>
            @foreach(Auth::user()->allTeams() as $switchTeam)
            <form method="POST" action="{{ route('current-team.update') }}">
                @method('PUT')
                @csrf
                <input type="hidden" name="team_id" value="{{ $switchTeam->id }}">
                <button type="submit" class="nav-item" @if(Auth::user()->isCurrentTeam($switchTeam)) aria-current="true" @endif>
                    @if(Auth::user()->isCurrentTeam($switchTeam))<x-icon name="check" class="nav-team-mark" />@else<span class="nav-team-mark" aria-hidden="true"></span>@endif
                    <span class="nav-label">{{ $switchTeam->name }}</span>
                </button>
            </form>
            @endforeach
            @endif
            @endif
            <div class="sidebar-divider"></div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="nav-item">
                    <x-icon name="logout" class="nav-icon" />
                    Log out
                </button>
            </form>
            @endauth
        </nav>

        @auth
        <div class="sidebar-footer">
            <div class="user-row">
                <div class="user-avatar">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</div>
                <div class="user-meta">
                    <div class="user-name">{{ Auth::user()->name }}</div>
                    <div class="user-team">{{ Auth::user()->currentTeam->name ?? 'Personal' }}</div>
                </div>
            </div>
        </div>
        @endauth
    </aside>

    <header class="topbar" data-shell-behind>
        <button type="button" id="shell-menu-button" class="shell-menu-button" aria-expanded="false" aria-controls="shell-drawer">
            <x-icon name="menu" />
            <span>Menu</span>
        </button>
        <div class="topbar-title">
            @isset($header)
                {{ $header }}
            @else
                Dot.Notify
            @endisset
        </div>
        @auth
        <span class="topbar-team">{{ Auth::user()->currentTeam->name ?? 'Personal' }}</span>
        <livewire:notify.notification-bell />
        @endauth
        <button type="button" id="theme-toggle" class="topbar-btn" title="Toggle dark mode" aria-label="Toggle dark mode">
            <x-icon name="moon" class="theme-moon" />
            <x-icon name="sun" class="theme-sun" />
        </button>
        <a href="{{ route('profile.show') }}" class="topbar-btn topbar-profile" title="Profile" aria-label="Profile">
            <x-icon name="user" />
        </a>
    </header>

    <div class="content-wrap" data-shell-behind>
        <main>{{ $slot }}</main>
    </div>

    @stack('modals')
    @livewireScripts

    <script>
        // Theme toggle: flips the scheme and remembers the choice on this device.
        (function () {
            var toggle = document.getElementById('theme-toggle');
            if (!toggle) { return; }
            toggle.addEventListener('click', function () {
                var dark = document.documentElement.classList.toggle('dark');
                try { localStorage.setItem('dot-theme', dark ? 'dark' : 'light'); } catch (e) {}
            });
        })();

        // Responsive shell: below 860px the sidebar is an off-canvas drawer.
        // Open: focus moves into the drawer, the page behind it goes inert and scroll is locked.
        // Close: Escape, backdrop, close button, following a nav link, or the viewport growing past the breakpoint.
        (function () {
            var root = document.documentElement;
            var button = document.getElementById('shell-menu-button');
            var drawer = document.getElementById('shell-drawer');
            var backdrop = document.getElementById('shell-backdrop');
            var closeButton = document.getElementById('shell-close');
            if (!button || !drawer) { return; }
            var wide = window.matchMedia('(min-width: 860px)');

            function behind() { return document.querySelectorAll('[data-shell-behind]'); }
            function isOpen() { return root.classList.contains('shell-open'); }

            function open() {
                if (wide.matches || isOpen()) { return; }
                root.classList.add('shell-open');
                button.setAttribute('aria-expanded', 'true');
                Array.prototype.forEach.call(behind(), function (el) { el.setAttribute('inert', ''); });
                (closeButton || drawer).focus();
            }

            function close(returnFocus) {
                if (!isOpen()) { return; }
                root.classList.remove('shell-open');
                button.setAttribute('aria-expanded', 'false');
                Array.prototype.forEach.call(behind(), function (el) { el.removeAttribute('inert'); });
                if (returnFocus) { button.focus(); }
            }

            button.addEventListener('click', function () { isOpen() ? close(true) : open(); });
            if (closeButton) { closeButton.addEventListener('click', function () { close(true); }); }
            if (backdrop) { backdrop.addEventListener('click', function () { close(true); }); }
            drawer.addEventListener('click', function (event) {
                if (event.target.closest && event.target.closest('a[href]')) { close(false); }
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && isOpen()) { close(true); }
            });
            var onWide = function (event) { if (event.matches) { close(false); } };
            if (wide.addEventListener) { wide.addEventListener('change', onWide); } else { wide.addListener(onWide); }
        })();
    </script>
</body>
</html>
