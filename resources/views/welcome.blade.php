<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Dot.Notify — The shared last mile of the Dot Ecosystem</title>
        <meta name="description" content="Send, track, and optimise notifications across email, SMS, push, webhook, Slack, and in-app — with a full audit trail from queue to delivery.">

        <!-- Favicon -->
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Work+Sans:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            :root {
                --ink: #12190f;
                --ink-soft: #182013;
                --amber: #f0c33a;
                --amber-soft: #f5d573;
                --green: #8bc34a;
                --green-soft: #a5d66b;
                --paper: #eef1ea;
                --mist: #9aab90;
                --line: rgba(238, 241, 234, 0.1);
                --font-display: 'Sora', system-ui, sans-serif;
                --font-body: 'Work Sans', system-ui, sans-serif;
                --font-mono: 'Space Mono', ui-monospace, monospace;
                --ease-out: cubic-bezier(0.23, 1, 0.32, 1);
            }
            html { background: var(--ink); }
            body { font-family: var(--font-body); background: var(--ink); color: var(--paper); }
            .font-display { font-family: var(--font-display); }
            .font-mono { font-family: var(--font-mono); }

            .press { transition: transform 160ms var(--ease-out); }
            .press:active { transform: scale(0.97); }

            @media (prefers-reduced-motion: no-preference) {
                .reveal {
                    opacity: 0;
                    transform: translateY(14px);
                    transition: opacity 600ms var(--ease-out), transform 600ms var(--ease-out);
                }
                .reveal.is-visible { opacity: 1; transform: translateY(0); }
            }
            @media (prefers-reduced-motion: reduce) {
                .reveal { opacity: 1; transform: none; }
            }

            @media (hover: hover) and (pointer: fine) {
                .row-hover:hover { background: rgba(238, 241, 234, 0.03); }
                .link-underline { background-size: 0% 1px; }
                .link-underline:hover { background-size: 100% 1px; }
            }
            .link-underline {
                background-image: linear-gradient(currentColor, currentColor);
                background-position: 0 100%;
                background-repeat: no-repeat;
                transition: background-size 220ms var(--ease-out);
            }
        </style>
    </head>
    <body class="antialiased">

        <!-- Nav -->
        <header
            id="site-header"
            class="fixed top-0 left-0 right-0 z-50 transition-colors duration-300 border-b border-transparent"
        >
            <nav class="max-w-[1400px] mx-auto px-5 sm:px-8 py-3 flex items-center justify-between">
                <a href="/" class="flex items-center gap-2.5 press">
                    <img src="{{ asset('images/logo-light.png') }}" alt="Dot.Notify" class="h-14 sm:h-[4.5rem] w-auto">
                </a>

                <div class="hidden md:flex items-center gap-8 font-mono text-[13px] tracking-wide uppercase text-[var(--mist)]">
                    <a href="#channels" class="link-underline hover:text-[var(--paper)] pb-0.5">Channels</a>
                    <a href="#pipeline" class="link-underline hover:text-[var(--paper)] pb-0.5">Delivery pipeline</a>
                </div>

                @if (Route::has('login'))
                    <div class="flex items-center gap-3">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="press flex items-center gap-2 px-5 py-2.5 bg-[var(--amber)] hover:bg-[var(--amber-soft)] text-[#12190f] text-sm font-display font-semibold rounded-lg transition-colors">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="hidden sm:block text-sm font-medium text-[var(--mist)] hover:text-[var(--paper)] transition-colors">
                                Sign in
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="press px-5 py-2.5 bg-[var(--amber)] hover:bg-[var(--amber-soft)] text-[#12190f] text-sm font-display font-semibold rounded-lg transition-colors">
                                    Get started
                                </a>
                            @endif
                        @endauth

                        <button id="menu-toggle" class="md:hidden press p-2 -mr-2 text-[var(--paper)]" aria-label="Toggle menu" aria-expanded="false">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path id="icon-open" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 7h16M4 12h16M4 17h16"></path>
                                <path id="icon-close" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                @endif
            </nav>

            <div id="mobile-menu" class="hidden md:hidden border-t border-[var(--line)] bg-[#12190f]">
                <div class="flex flex-col px-5 py-4 gap-1 font-mono text-sm uppercase tracking-wide">
                    <a href="#channels" class="px-3 py-2.5 text-[var(--mist)] hover:text-[var(--paper)]">Channels</a>
                    <a href="#pipeline" class="px-3 py-2.5 text-[var(--mist)] hover:text-[var(--paper)]">Delivery pipeline</a>
                    @guest
                        <a href="{{ route('login') }}" class="px-3 py-2.5 text-[var(--mist)] hover:text-[var(--paper)]">Sign in</a>
                    @endguest
                </div>
            </div>
        </header>

        <!-- Hero -->
        <section class="relative min-h-[100dvh] flex items-end overflow-hidden">
            <!-- Photo: close-up of a cell phone with a message on it, by Jonas Leupe,
            unsplash.com/photos/a-close-up-of-a-cell-phone-with-a-message-on-it-gpupazK2Ins -->
            <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1639592660386-d8c1d801e616?q=80&w=2400&auto=format&fit=crop');"></div>
            <div class="absolute inset-0" style="background: linear-gradient(100deg, var(--ink) 0%, var(--ink) 32%, rgba(18,25,15,0.6) 55%, rgba(18,25,15,0.35) 75%, rgba(18,25,15,0.18) 100%);"></div>
            <div class="absolute inset-0" style="background: radial-gradient(ellipse 80% 60% at 15% 0%, rgba(139,195,74,0.10) 0%, transparent 60%);"></div>

            <!-- Signature element: line-art bell + ring/ping arcs — echoes the logo's own bell icon and the "signal" every notification is -->
            <svg class="hidden lg:block absolute right-[5%] bottom-[8%] h-[65%] w-auto opacity-[0.16] pointer-events-none" viewBox="0 0 240 300" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M120 40C90 40 70 65 70 100V150L50 190H190L170 150V100C170 65 150 40 120 40Z" stroke="#eef1ea" stroke-width="3" stroke-linejoin="round"/>
                <path d="M95 190C95 205 106 215 120 215C134 215 145 205 145 190" stroke="#eef1ea" stroke-width="3"/>
                <circle cx="120" cy="40" r="10" stroke="#eef1ea" stroke-width="3"/>
                <!-- ping rings, offset upper-right, echoing the "1" notification badge on the logo -->
                <path d="M172 58C182 58 190 66 190 76" stroke="#f0c33a" stroke-width="3" stroke-linecap="round"/>
                <path d="M172 40C192 40 208 56 208 76" stroke="#f0c33a" stroke-width="3" stroke-linecap="round" opacity="0.6"/>
                <path d="M172 22C202 22 226 46 226 76" stroke="#f0c33a" stroke-width="3" stroke-linecap="round" opacity="0.35"/>
            </svg>

            <div class="relative z-10 max-w-[1400px] mx-auto px-5 sm:px-8 pt-32 pb-16 sm:pb-20 w-full">
                <div class="max-w-2xl reveal" data-reveal>
                    <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--amber)] mb-6">
                        The shared last mile
                    </p>

                    <h1 class="font-display font-bold text-4xl sm:text-5xl lg:text-6xl leading-[1.05] tracking-tight text-[var(--paper)] mb-6">
                        Every channel.<br>One place it lands.
                    </h1>

                    <p class="text-lg text-[var(--mist)] leading-relaxed max-w-xl mb-10">
                        A platform tells us an event happened. We decide, per team-configured rule, who gets told, on what channel, with what copy — email, SMS, push, webhook, Slack, or in-app — and log the full journey from queue to delivery.
                    </p>

                    @guest
                        <div class="flex flex-wrap items-center gap-4">
                            <a href="{{ route('register') }}" class="press px-7 py-3.5 bg-[var(--amber)] hover:bg-[var(--amber-soft)] text-[#12190f] font-display font-semibold rounded-lg transition-colors">
                                Get started
                            </a>
                            <a href="#channels" class="press flex items-center gap-2 px-7 py-3.5 text-[var(--paper)] font-medium rounded-lg border border-[var(--line)] hover:border-[var(--mist)] transition-colors">
                                See what it routes
                            </a>
                        </div>
                    @endguest
                </div>
            </div>

            <!-- Channel strip — real delivery channels from wiki.md §3, not fabricated metrics -->
            <div class="relative z-10 w-full border-t border-[var(--line)] bg-[#12190f]/60 backdrop-blur-sm">
                <div class="max-w-[1400px] mx-auto px-5 sm:px-8 py-4 flex flex-wrap gap-x-8 gap-y-2 font-mono text-[11px] tracking-[0.14em] uppercase text-[var(--mist)]">
                    <span>Email</span>
                    <span class="text-[var(--green)]">·</span>
                    <span>SMS</span>
                    <span class="text-[var(--green)]">·</span>
                    <span>Push</span>
                    <span class="text-[var(--green)]">·</span>
                    <span>Webhook</span>
                    <span class="text-[var(--green)]">·</span>
                    <span>Slack</span>
                    <span class="text-[var(--green)]">·</span>
                    <span>In-app</span>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section id="channels" class="py-24 sm:py-28 px-5 sm:px-8">
            <div class="max-w-[1400px] mx-auto">
                <div class="max-w-xl mb-16 reveal" data-reveal>
                    <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--amber)] mb-4">One pipeline, every channel</p>
                    <h2 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--paper)] leading-tight">
                        Channels, templates, rules, and a log you can trust
                    </h2>
                </div>

                <div class="grid md:grid-cols-2 border-t border-[var(--line)]">
                    @php
                        $features = [
                            ['tag' => 'Channels', 'title' => 'Multi-channel delivery', 'body' => 'Configure email, SMS, push, webhook, Slack, and in-app delivery per team, and route to whichever one fits the moment.'],
                            ['tag' => 'Templates', 'title' => 'AI-assisted templates', 'body' => 'Draft message copy with variable interpolation, reused across every rule and channel. Falls back to a usable stub if AI drafting is unavailable — it never blocks a send.'],
                            ['tag' => 'Routing', 'title' => 'Rule-based routing', 'body' => 'Map any trigger event to a template and a channel, with conditions — per team, not a global default.'],
                            ['tag' => 'Audit', 'title' => 'Full delivery audit trail', 'body' => 'Every notification\'s journey — queued, sent, delivered, opened, clicked, or failed — logged per recipient.'],
                            ['tag' => 'Inbound', 'title' => 'Signature-verified webhooks', 'body' => 'A per-webhook token plus an HMAC-SHA256 signature translate a third-party event into one of your own trigger events.'],
                            ['tag' => 'Volume', 'title' => 'Batches & schedules', 'body' => 'Bulk sends with progress tracking, plus recurring sends tied to a cron expression and timezone.'],
                        ];
                    @endphp
                    @foreach ($features as $i => $f)
                        <div class="row-hover border-b border-[var(--line)] {{ $i % 2 === 0 ? 'md:border-r' : '' }} px-1 py-8 sm:py-10 transition-colors reveal" data-reveal>
                            <p class="font-mono text-[11px] tracking-[0.14em] uppercase text-[var(--amber)] mb-3">{{ $f['tag'] }}</p>
                            <h3 class="font-display font-semibold text-xl text-[var(--paper)] mb-2.5">{{ $f['title'] }}</h3>
                            <p class="text-[var(--mist)] leading-relaxed max-w-md">{{ $f['body'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Delivery pipeline — real status walk from wiki.md §4, styled as the platform's own data artifact -->
        <section id="pipeline" class="py-24 sm:py-28 px-5 sm:px-8 bg-[var(--ink-soft)] border-y border-[var(--line)]">
            <div class="max-w-[1400px] mx-auto">
                <div class="grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)] gap-12 lg:gap-20">
                    <div class="reveal" data-reveal>
                        <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--amber)] mb-4">Delivery pipeline</p>
                        <h2 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--paper)] leading-tight mb-5">
                            We're the only platform that sees the whole journey
                        </h2>
                        <p class="text-[var(--mist)] leading-relaxed max-w-sm">
                            Every send walks the same statuses, whatever triggered it — a batch, a rule, or a signed inbound webhook.
                        </p>
                    </div>

                    <div class="reveal overflow-x-auto" data-reveal>
                        <div class="flex items-stretch gap-0 min-w-[560px] font-mono text-xs uppercase tracking-[0.1em]">
                            @php
                                $steps = [
                                    ['label' => 'Queued', 'note' => 'Handed to the pipeline'],
                                    ['label' => 'Sent', 'note' => 'Given to the channel driver'],
                                    ['label' => 'Delivered', 'note' => 'Channel confirmed receipt'],
                                    ['label' => 'Opened / Clicked', 'note' => 'Recipient engaged'],
                                ];
                            @endphp
                            @foreach ($steps as $i => $s)
                                <div class="flex-1 {{ $i > 0 ? 'border-l border-[var(--line)] pl-5' : '' }} {{ $i < count($steps) - 1 ? 'pr-5' : '' }}">
                                    <div class="flex items-center gap-2 mb-3">
                                        <span class="text-[var(--green)]">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                                        <span class="text-[var(--paper)] font-display normal-case text-sm font-semibold tracking-normal">{{ $s['label'] }}</span>
                                    </div>
                                    <p class="text-[var(--mist)] normal-case tracking-normal leading-relaxed">{{ $s['note'] }}</p>
                                </div>
                            @endforeach
                        </div>
                        <p class="mt-8 text-sm text-[var(--mist)] normal-case font-body max-w-md">
                            A send can also branch to <span class="text-[var(--paper)]">failed</span> or <span class="text-[var(--paper)]">bounced</span>, with a recorded failure reason — that's a result, not a gap in the log.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="relative py-28 sm:py-36 px-5 sm:px-8 overflow-hidden">
            <div class="absolute inset-0" style="background: radial-gradient(ellipse 70% 50% at 50% 100%, rgba(240,195,58,0.08) 0%, transparent 65%), var(--ink);"></div>

            <div class="relative z-10 max-w-2xl mx-auto text-center reveal" data-reveal>
                <h2 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--paper)] leading-tight mb-5">
                    Stop guessing what got through
                </h2>
                <p class="text-[var(--mist)] leading-relaxed mb-10 max-w-lg mx-auto">
                    Route every event to the right channel, with copy your team controls, and a delivery log you can actually trust.
                </p>

                @guest
                    <div class="flex flex-wrap justify-center gap-4">
                        <a href="{{ route('register') }}" class="press px-8 py-3.5 bg-[var(--amber)] hover:bg-[var(--amber-soft)] text-[#12190f] font-display font-semibold rounded-lg transition-colors">
                            Get started
                        </a>
                        <a href="{{ route('login') }}" class="press px-8 py-3.5 text-[var(--paper)] font-medium rounded-lg border border-[var(--line)] hover:border-[var(--mist)] transition-colors">
                            Sign in
                        </a>
                    </div>
                @endguest
            </div>
        </section>

        <!-- Footer -->
        <footer class="py-14 px-5 sm:px-8 border-t border-[var(--line)]">
            <div class="max-w-[1400px] mx-auto flex flex-col sm:flex-row items-center justify-between gap-6">
                <a href="/" class="flex items-center gap-2.5">
                    <img src="{{ asset('images/logo-light.png') }}" alt="Dot.Notify" class="h-11 w-auto opacity-90">
                </a>
                <div class="flex items-center gap-6 font-mono text-xs tracking-wide uppercase text-[var(--mist)]">
                    <a href="{{ route('policy.show') }}" class="hover:text-[var(--paper)] transition-colors">Privacy</a>
                    <a href="{{ route('cookies') }}" class="hover:text-[var(--paper)] transition-colors">Cookies</a>
                    <a href="{{ route('terms.show') }}" class="hover:text-[var(--paper)] transition-colors">Terms</a>
                </div>
                <p class="font-mono text-xs tracking-wide text-[var(--mist)]">
                    &copy; {{ date('Y') }} Dot.Notify. The shared last mile of the Dot Ecosystem.
                </p>
            </div>
        </footer>

        <script>
            // Nav scroll state + mobile menu (vanilla JS — no Alpine dependency on this guest page)
            const header = document.getElementById('site-header');
            const onScroll = () => {
                header.classList.toggle('bg-[#12190f]/95', window.pageYOffset > 24);
                header.classList.toggle('backdrop-blur-md', window.pageYOffset > 24);
                header.classList.toggle('border-[var(--line)]', window.pageYOffset > 24);
                header.classList.toggle('border-transparent', window.pageYOffset <= 24);
            };
            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();

            const menuToggle = document.getElementById('menu-toggle');
            const mobileMenu = document.getElementById('mobile-menu');
            const iconOpen = document.getElementById('icon-open');
            const iconClose = document.getElementById('icon-close');
            if (menuToggle) {
                menuToggle.addEventListener('click', () => {
                    const isOpen = !mobileMenu.classList.contains('hidden');
                    mobileMenu.classList.toggle('hidden', isOpen);
                    iconOpen.classList.toggle('hidden', !isOpen);
                    iconClose.classList.toggle('hidden', isOpen);
                    menuToggle.setAttribute('aria-expanded', String(!isOpen));
                });
            }

            if (window.matchMedia('(prefers-reduced-motion: no-preference)').matches && 'IntersectionObserver' in window) {
                const io = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-visible');
                            io.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
                document.querySelectorAll('[data-reveal]').forEach((el) => io.observe(el));
            } else {
                document.querySelectorAll('[data-reveal]').forEach((el) => el.classList.add('is-visible'));
            }
        </script>
    </body>
</html>
