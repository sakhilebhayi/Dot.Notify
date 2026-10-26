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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class', corePlugins: { preflight: false } }</script>
    <script>
        // Dark mode: this dashboard's own markup is intentionally fixed-dark (inline styles,
        // not Tailwind dark: classes), so this toggle mainly affects Jetstream-rendered pages
        // (Profile, Team Settings) which do use dark: classes. Kept for theme consistency
        // ecosystem-wide and so those subpages respect the user's light/dark preference.
        (function () {
            const stored = localStorage.getItem('dot-theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (stored === 'dark' || (!stored && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    <style>
        :root { --accent: #fb923c; --accent-rgb: 251,146,60; --topbar-h: 54px; --sidebar-w: 260px; }
        *, *::before, *::after { box-sizing: border-box; }
        body { margin:0; background:#09090b; color:#f4f4f5; font-family:'Inter',system-ui,sans-serif; font-size:14px; line-height:1.5; }
        .material-symbols-rounded { font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24; line-height:1; user-select:none; }
        [x-cloak] { display:none!important; }

        /* Sidebar */
        .sidebar { position:fixed; left:0; top:0; width:var(--sidebar-w); height:100vh; height:100dvh; background:#0d0d10; border-right:1px solid rgba(255,255,255,0.06); display:flex; flex-direction:column; z-index:40; overflow:hidden; }
        .sidebar:focus { outline:none; }
        .sidebar::before { content:''; position:absolute; top:-80px; left:-80px; width:320px; height:320px; background:radial-gradient(circle, rgba(251,146,60,0.1) 0%, transparent 65%); pointer-events:none; }

        .sidebar-brand { padding:20px 18px 14px; display:flex; align-items:center; gap:11px; flex-shrink:0; }
        .brand-icon { width:36px; height:36px; border-radius:10px; background:rgba(251,146,60,0.12); border:1px solid rgba(251,146,60,0.22); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .brand-icon .material-symbols-rounded { font-size:18px; color:#fb923c; }
        .brand-name { font-family:'Syne',sans-serif; font-size:14.5px; font-weight:700; color:#f4f4f5; letter-spacing:-0.01em; line-height:1.2; }
        .brand-status { display:flex; align-items:center; gap:5px; margin-top:3px; }
        .live-dot { width:6px; height:6px; border-radius:50%; background:#fb923c; flex-shrink:0; animation:live-pulse 2.8s ease-in-out infinite; }
        @keyframes live-pulse { 0%,100% { opacity:1; box-shadow:0 0 0 0 rgba(251,146,60,0.45); } 60% { opacity:.6; box-shadow:0 0 0 5px rgba(251,146,60,0); } }
        .brand-subtitle { font-size:10px; font-weight:500; color:#3f3f46; text-transform:uppercase; letter-spacing:0.09em; }

        .sidebar-divider { height:1px; background:rgba(255,255,255,0.06); margin:4px 14px 8px; }
        .sidebar-nav { padding:0 10px; flex:1; overflow-y:auto; scrollbar-width:none; }
        .sidebar-nav::-webkit-scrollbar { display:none; }
        .nav-section-label { font-size:10px; font-weight:600; color:#3f3f46; text-transform:uppercase; letter-spacing:0.1em; padding:14px 8px 5px; }
        .nav-item { display:flex; align-items:center; gap:9px; padding:7.5px 10px; border-radius:8px; font-size:13px; font-weight:500; color:#71717a; text-decoration:none; transition:background .13s,color .13s,transform .13s; margin-bottom:1px; }
        .nav-item:hover { background:rgba(255,255,255,0.05); color:#d4d4d8; transform:translateX(1px); }
        .nav-item.active { background:rgba(251,146,60,0.1); color:#fb923c; font-weight:600; }
        .nav-icon { font-size:17px; width:20px; text-align:center; flex-shrink:0; }

        .sidebar-footer { padding:10px 14px 14px; border-top:1px solid rgba(255,255,255,0.06); flex-shrink:0; }
        .user-row { display:flex; align-items:center; gap:9px; padding:8px 6px; border-radius:8px; }
        .user-avatar { width:28px; height:28px; border-radius:50%; background:rgba(251,146,60,0.18); border:1px solid rgba(251,146,60,0.28); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:#fb923c; flex-shrink:0; font-family:'Syne',sans-serif; }
        .user-name { font-size:12px; font-weight:600; color:#d4d4d8; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .user-team { font-size:10px; color:#52525b; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

        /* Topbar */
        .topbar { position:fixed; top:0; left:var(--sidebar-w); right:0; height:var(--topbar-h); background:rgba(9,9,11,0.85); backdrop-filter:blur(14px); -webkit-backdrop-filter:blur(14px); border-bottom:1px solid rgba(255,255,255,0.06); display:flex; align-items:center; padding:0 22px; z-index:30; gap:12px; }
        .topbar-title { font-family:'Syne',sans-serif; font-size:14px; font-weight:700; color:#f4f4f5; flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .topbar-team { font-size:11px; color:#52525b; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.07); border-radius:6px; padding:3px 8px; font-weight:500; white-space:nowrap; min-width:0; max-width:22rem; overflow:hidden; text-overflow:ellipsis; }
        .topbar-btn { width:30px; height:30px; border-radius:7px; border:1px solid rgba(255,255,255,0.08); background:rgba(255,255,255,0.04); display:flex; align-items:center; justify-content:center; color:#71717a; cursor:pointer; transition:background .13s,color .13s; text-decoration:none; flex-shrink:0; }
        .topbar-btn:hover { background:rgba(255,255,255,0.09); color:#d4d4d8; }
        .topbar-btn .material-symbols-rounded { font-size:17px; }

        /* Content */
        .content-wrap { margin-left:var(--sidebar-w); padding-top:var(--topbar-h); min-height:100vh; }

        /* Shared UI tokens */
        .dot-card { background:#141416; border:1px solid rgba(255,255,255,0.07); border-radius:12px; }
        .dot-card:hover { border-color:rgba(255,255,255,0.11); }
        .metric-val { font-family:'JetBrains Mono',monospace; font-weight:500; letter-spacing:-0.02em; }
        .dot-input { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:8px; color:#f4f4f5; font-family:'Inter',sans-serif; font-size:13px; padding:8px 12px; width:100%; transition:border-color .15s,box-shadow .15s; outline:none; }
        .dot-input:focus { border-color:rgba(251,146,60,0.45); box-shadow:0 0 0 3px rgba(251,146,60,0.07); }
        .dot-input::placeholder { color:#3f3f46; }
        .dot-btn { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; transition:all .14s; border:none; text-decoration:none; font-family:'Inter',sans-serif; }
        .dot-btn-primary { background:#fb923c; color:#09090b; }
        .dot-btn-primary:hover { filter:brightness(1.1); }
        .dot-btn-ghost { background:rgba(255,255,255,0.06); color:#a1a1aa; border:1px solid rgba(255,255,255,0.08); }
        .dot-btn-ghost:hover { background:rgba(255,255,255,0.1); color:#f4f4f5; }
        .btn-tall { min-height:36px; }
        .dot-badge { display:inline-flex; align-items:center; padding:2px 8px; border-radius:100px; font-size:11px; font-weight:600; }
        .dot-badge-accent { background:rgba(251,146,60,0.12); color:#fb923c; }
        select.dot-input option { background:#1a1a1f; }

        /* Responsive shell. Breakpoint: 860px. At or above it the sidebar is fixed beside the content (the layout above);
           below it the sidebar becomes an off-canvas drawer opened from the top bar. Hooks: #shell-menu-button,
           #shell-drawer, #shell-backdrop, .topbar, .table-scroll. See README.md. */
        .shell-menu-button, .shell-close, .shell-backdrop { display:none; }
        .shell-menu-button { align-items:center; gap:6px; min-height:44px; padding:0 12px 0 10px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); background:rgba(255,255,255,0.05); color:#d4d4d8; font:inherit; font-size:13px; font-weight:600; cursor:pointer; flex-shrink:0; }
        .shell-menu-button .material-symbols-rounded { font-size:20px; }
        .shell-close { align-items:center; justify-content:center; width:44px; height:44px; margin-left:auto; border-radius:8px; border:1px solid rgba(255,255,255,0.1); background:rgba(255,255,255,0.05); color:#d4d4d8; cursor:pointer; flex-shrink:0; }
        .shell-menu-button:hover, .shell-close:hover { background:rgba(255,255,255,0.1); color:#f4f4f5; }
        .shell-menu-button:focus-visible, .shell-close:focus-visible, .topbar-btn:focus-visible, .nav-item:focus-visible, .dot-btn:focus-visible { outline:2px solid #fb923c; outline-offset:2px; }

        /* Account links in the sidebar (team settings, switch team, log out): buttons that look like nav items */
        button.nav-item { width:100%; border:0; background:none; font-family:inherit; text-align:left; cursor:pointer; }
        .nav-item[aria-current="true"] { color:#f4f4f5; font-weight:600; }
        .nav-item .nav-team-mark { width:20px; text-align:center; flex-shrink:0; color:#fb923c; font-size:14px; line-height:1; }
        .nav-item .nav-label { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

        /* Shared responsive helpers */
        .page { padding:2rem 2.5rem 3rem; }
        .kpi-grid { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:1rem; margin-bottom:2rem; }
        .kpi-card { padding:1.25rem 1.5rem; }
        .two-col { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:1.25rem; margin-top:1.25rem; }
        .dot-card { min-width:0; }
        .dot-card-pad { padding:1.5rem; }
        .card-head { display:flex; align-items:center; justify-content:space-between; gap:0.75rem; flex-wrap:wrap; margin-bottom:1.25rem; }
        .dot-row { display:flex; align-items:center; justify-content:space-between; gap:0.5rem 0.75rem; flex-wrap:wrap; padding:0.65rem 0.75rem; background:rgba(255,255,255,0.03); border-radius:8px; }
        .dot-row-main { min-width:0; flex:1 1 12rem; }
        .dot-row-actions { display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap; margin-left:auto; }
        .row-break { overflow-wrap:anywhere; }
        .form-grid { display:grid; gap:0.75rem; margin-bottom:1.25rem; }
        .form-grid-2 { grid-template-columns:minmax(0,1fr) minmax(0,1fr); }
        .form-grid-3 { grid-template-columns:minmax(0,1fr) minmax(0,1fr) auto; align-items:end; }
        .form-span { grid-column:1 / -1; }
        .toolbar { display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap; }
        .toolbar-search { position:relative; }
        .toolbar-search .dot-input { font-size:11px; padding:5px 8px 5px 26px; width:200px; }
        .toolbar-select { font-size:11px; padding:4px 8px; width:auto; }
        .bell-wrap { position:relative; }
        .bell-panel { position:absolute; top:calc(100% + 8px); right:0; width:320px; max-height:420px; overflow-y:auto; background:#141416; border:1px solid rgba(255,255,255,0.09); border-radius:10px; box-shadow:0 12px 32px rgba(0,0,0,0.45); z-index:50; }
        /* Wide data (real tables) scrolls inside its own container, never the page. Wrap a <table> in .table-scroll. */
        .table-scroll { max-width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch; }
        .table-scroll > table { min-width:36rem; width:100%; border-collapse:collapse; }

        @media (max-width: 1099.98px) {
            .two-col { grid-template-columns:minmax(0,1fr); }
            .kpi-grid { grid-template-columns:repeat(3,minmax(0,1fr)); }
            .kpi-grid > .kpi-card:last-child:nth-child(3n+2) { grid-column:span 2; }
        }

        @media (max-width: 859.98px) {
            .shell-menu-button, .shell-close { display:inline-flex; }
            .shell-backdrop { display:block; position:fixed; inset:0; z-index:35; background:rgba(0,0,0,0.6); opacity:0; visibility:hidden; transition:opacity .2s ease, visibility 0s linear .2s; }
            html.shell-open .shell-backdrop { opacity:1; visibility:visible; transition:opacity .2s ease, visibility 0s linear 0s; }
            html.shell-open, html.shell-open body { overflow:hidden; }

            /* Closed drawer is visibility:hidden, so it is out of the tab order and hidden from assistive tech, not just off-screen */
            .sidebar { width:min(20rem, calc(100vw - 3rem)); transform:translateX(-100%); visibility:hidden; transition:transform .22s ease, visibility 0s linear .22s; overscroll-behavior:contain; }
            html.shell-open .sidebar { transform:translateX(0); visibility:visible; transition:transform .22s ease, visibility 0s linear 0s; }
            .sidebar-brand { padding-right:12px; }
            .sidebar-nav { overscroll-behavior:contain; }
            .nav-item { min-height:44px; padding-top:0; padding-bottom:0; font-size:14px; }
            .nav-item:hover { transform:none; }

            .topbar { left:0; padding:0 12px; gap:8px; }
            .topbar-btn { width:44px; height:44px; }
            .topbar-btn .material-symbols-rounded { font-size:20px; }
            .topbar-profile { display:none; }
            .content-wrap { margin-left:0; }
            .bell-wrap { position:static; }
            .bell-panel { left:12px; right:12px; width:auto; max-height:min(26rem, calc(100vh - var(--topbar-h) - 24px)); max-height:min(26rem, calc(100dvh - var(--topbar-h) - 24px)); top:calc(var(--topbar-h) + 6px); }

            .page { padding:1.25rem 1rem 2rem; }
            .kpi-grid { grid-template-columns:repeat(2,minmax(0,1fr)); gap:0.75rem; margin-bottom:1.25rem; }
            .kpi-grid > .kpi-card:last-child:nth-child(odd) { grid-column:1 / -1; }
            .kpi-card { padding:1rem; }
            .dot-card-pad { padding:1rem; }
            .two-col { gap:1rem; }
            .form-grid-2, .form-grid-3 { grid-template-columns:minmax(0,1fr); }
            .form-grid-3 { align-items:stretch; }
            .toolbar { width:100%; }
            .toolbar-search { flex:1 1 12rem; }
            .toolbar-search .dot-input { width:100%; }
            .toolbar-select { flex:0 1 auto; }

            /* Form controls: 16px stops iOS zooming on focus, 44px is a comfortable touch target */
            .dot-input, .toolbar-search .dot-input, .toolbar-select { font-size:16px; min-height:44px; }
            textarea.dot-input { min-height:5rem; }
            .dot-btn { min-height:44px; min-width:44px; justify-content:center; }
            .row-action { min-height:44px; min-width:44px; }
            /* Jetstream forms (profile, team) that render inside this layout */
            .content-wrap input:not([type=checkbox]):not([type=radio]):not([type=hidden]):not([type=file]), .content-wrap select, .content-wrap textarea { min-height:44px; font-size:16px; }
            .content-wrap button.inline-flex { min-height:44px; }
            .ca-details summary { min-height:44px; display:flex; align-items:center; }
        }

        @media (max-width: 519.98px) {
            .topbar-team { display:none; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration:0.01ms !important; animation-iteration-count:1 !important; transition-duration:0.01ms !important; scroll-behavior:auto !important; }
            .sidebar, .shell-backdrop, .nav-item, .topbar-btn, .dot-btn { transition:none !important; }
            .live-dot { animation:none; }
        }
    </style>
    @livewireStyles
    {{-- No stand-alone Alpine here: Livewire 3 bundles and starts its own (@livewireScripts). A second copy
         replaces window.Alpine and breaks every Livewire re-render ('Alpine.cloneNode is not a function'). --}}
</head>
<body>
    <div data-shell-behind><x-banner /></div>

    <div id="shell-backdrop" class="shell-backdrop" aria-hidden="true"></div>

    <aside class="sidebar" id="shell-drawer" aria-label="Sidebar" tabindex="-1">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <span class="material-symbols-rounded">notifications_active</span>
            </div>
            <div>
                <div class="brand-name">Dot.Notify</div>
                <div class="brand-status">
                    <div class="live-dot"></div>
                    <span class="brand-subtitle">Notification Hub</span>
                </div>
            </div>
            <button type="button" id="shell-close" class="shell-close" aria-label="Close menu">
                <span class="material-symbols-rounded" aria-hidden="true">close</span>
            </button>
        </div>

        <div class="sidebar-divider"></div>

        <nav class="sidebar-nav" aria-label="Main">
            <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>
                <span class="material-symbols-rounded nav-icon">dashboard</span>
                Dashboard
            </a>
            <a href="{{ route('connected-apps') }}" class="nav-item {{ request()->routeIs('connected-apps') ? 'active' : '' }}" @if(request()->routeIs('connected-apps')) aria-current="page" @endif>
                <span class="material-symbols-rounded nav-icon">hub</span>
                Connected apps
            </a>
            <div class="sidebar-divider" style="margin:10px 0;"></div>
            <a href="{{ route('profile.show') }}" class="nav-item {{ request()->routeIs('profile.show') ? 'active' : '' }}" @if(request()->routeIs('profile.show')) aria-current="page" @endif>
                <span class="material-symbols-rounded nav-icon">manage_accounts</span>
                Profile &amp; Settings
            </a>

            @auth
            @if(Auth::user()->currentTeam)
            <div class="nav-section-label">Team</div>
            <a href="{{ route('teams.show', Auth::user()->currentTeam->id) }}" class="nav-item {{ request()->routeIs('teams.show') ? 'active' : '' }}" @if(request()->routeIs('teams.show')) aria-current="page" @endif>
                <span class="material-symbols-rounded nav-icon">group</span>
                Team settings
            </a>
            @can('create', Laravel\Jetstream\Jetstream::newTeamModel())
            <a href="{{ route('teams.create') }}" class="nav-item {{ request()->routeIs('teams.create') ? 'active' : '' }}" @if(request()->routeIs('teams.create')) aria-current="page" @endif>
                <span class="material-symbols-rounded nav-icon">group_add</span>
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
                    <span class="nav-team-mark" aria-hidden="true">{{ Auth::user()->isCurrentTeam($switchTeam) ? '●' : '' }}</span>
                    <span class="nav-label">{{ $switchTeam->name }}</span>
                </button>
            </form>
            @endforeach
            @endif
            @endif
            <div class="sidebar-divider" style="margin:10px 0;"></div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="nav-item">
                    <span class="material-symbols-rounded nav-icon">logout</span>
                    Log out
                </button>
            </form>
            @endauth
        </nav>

        @auth
        <div class="sidebar-footer">
            <div class="user-row">
                <div class="user-avatar">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</div>
                <div style="min-width:0;flex:1;">
                    <div class="user-name">{{ Auth::user()->name }}</div>
                    <div class="user-team">{{ Auth::user()->currentTeam->name ?? 'Personal' }}</div>
                </div>
            </div>
        </div>
        @endauth
    </aside>

    <header class="topbar" data-shell-behind>
        <button type="button" id="shell-menu-button" class="shell-menu-button" aria-expanded="false" aria-controls="shell-drawer">
            <span class="material-symbols-rounded" aria-hidden="true">menu</span>
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
        <button
            type="button"
            onclick="document.documentElement.classList.toggle('dark'); localStorage.setItem('dot-theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');"
            class="topbar-btn"
            title="Toggle dark mode"
            aria-label="Toggle dark mode"
        >
            <span class="material-symbols-rounded" aria-hidden="true">dark_mode</span>
        </button>
        <a href="{{ route('profile.show') }}" class="topbar-btn topbar-profile" title="Profile" aria-label="Profile">
            <span class="material-symbols-rounded" aria-hidden="true">account_circle</span>
        </a>
    </header>

    <div class="content-wrap" data-shell-behind>
        <main>{{ $slot }}</main>
    </div>

    @stack('modals')
    @livewireScripts

    <script>
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
