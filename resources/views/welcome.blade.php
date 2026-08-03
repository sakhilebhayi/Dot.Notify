<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dot.Notify — The Shared Last Mile of the Dot Ecosystem</title>
    <meta name="description" content="Send, track, and optimise notifications across email, SMS, push, webhook, Slack, and in-app — with a full audit trail from queue to delivery.">

    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{background:#0a0f14;color:#e4e4e7;font-family:'Inter',system-ui,sans-serif;font-size:15px;line-height:1.6;overflow-x:hidden}
        :root{--accent:#38bdf8;--accent-soft:rgba(56,189,248,0.12)}
        a{color:inherit}
        h1,h2,h3{font-family:'Space Grotesk',sans-serif}
        .wrap{max-width:1180px;margin:0 auto;padding-inline:max(1.5rem,5vw)}
        .btn-primary{display:inline-flex;align-items:center;gap:8px;padding:12px 26px;border-radius:10px;background:var(--accent);color:#0a0f14;font-weight:700;text-decoration:none;transition:filter .15s}
        .btn-primary:hover{filter:brightness(1.12)}
        .btn-ghost{display:inline-flex;align-items:center;gap:8px;padding:12px 26px;border-radius:10px;background:transparent;border:1px solid rgba(255,255,255,0.14);color:#a1a1aa;text-decoration:none;font-weight:600;transition:all .15s}
        .btn-ghost:hover{border-color:rgba(56,189,248,0.5);color:#f4f4f5}
        .badge{display:inline-flex;align-items:center;gap:7px;padding:6px 14px;background:var(--accent-soft);border:1px solid rgba(56,189,248,0.3);border-radius:100px;font-size:12px;font-weight:600;color:#7dd3fc}
        .card{background:#111820;border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:1.75rem;transition:border-color .2s}
        .card:hover{border-color:rgba(56,189,248,0.35)}
        .card-icon{width:44px;height:44px;border-radius:12px;background:var(--accent-soft);border:1px solid rgba(56,189,248,0.25);display:flex;align-items:center;justify-content:center;margin-bottom:1.1rem;font-size:20px}
        .chip{display:inline-flex;align-items:center;padding:5px 12px;border-radius:8px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);font-size:12.5px;color:#d4d4d8;margin:4px 6px 4px 0;}
    </style>
</head>
<body>
    {{-- Nav --}}
    <nav style="position:sticky;top:0;z-index:50;background:rgba(10,15,20,0.85);backdrop-filter:blur(14px);border-bottom:1px solid rgba(255,255,255,0.06);">
        <div class="wrap" style="height:64px;display:flex;align-items:center;justify-content:space-between;">
            <a href="/" style="display:flex;align-items:center;gap:10px;text-decoration:none;">
                <img src="{{ asset('images/logo.png') }}" alt="Dot.Notify" style="height:34px;width:auto;">
                <span style="font-family:'Space Grotesk',sans-serif;font-size:16px;font-weight:700;letter-spacing:-0.01em;color:#f4f4f5;">Dot.Notify</span>
            </a>
            <div style="display:flex;align-items:center;gap:12px;">
                @if (Route::has('login'))
                    @auth
                        <a href="{{ url('/dashboard') }}" class="btn-primary" style="padding:9px 20px;font-size:14px;">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="btn-ghost" style="padding:9px 20px;font-size:14px;">Sign in</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="btn-primary" style="padding:9px 20px;font-size:14px;">Get started</a>
                        @endif
                    @endauth
                @endif
            </div>
        </div>
    </nav>

    {{-- Hero --}}
    <section style="position:relative;padding:8rem max(1.5rem,5vw) 6rem;overflow:hidden;">
        <!-- Photographic Background: real hand-holding-smartphone-with-notifications photo by Andrey Matveev (@zelebb), unsplash.com/photos/a-hand-holds-a-smartphone-showing-notifications-QGKCe8C3D8g -->
        <div style="position:absolute;inset:0;background-image:url('https://images.unsplash.com/photo-1752218804012-3e7db92274ec?q=80&amp;w=2400&amp;auto=format&amp;fit=crop');background-size:cover;background-position:center;"></div>
        <div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(10,15,20,0.88) 0%,rgba(10,15,20,0.93) 55%,#0a0f14 100%);"></div>
        <div style="position:absolute;inset:0;background:linear-gradient(90deg,#0a0f14 0%,rgba(10,15,20,0.55) 45%,transparent 80%);"></div>

        <div class="wrap" style="position:relative;max-width:760px;">
            <div class="badge">
                <span>The Shared Last Mile</span>
            </div>
            <h1 style="font-size:clamp(2.3rem,5.5vw,3.6rem);font-weight:700;color:#f4f4f5;line-height:1.12;letter-spacing:-0.02em;margin:1.4rem 0 1.3rem;">
                Every channel. One place<br>to see what actually lands
            </h1>
            <p style="font-size:1.08rem;color:#a1a1aa;max-width:600px;margin-bottom:2.2rem;line-height:1.7;">
                Dot.Notify sends, tracks, and optimises notifications on behalf of the rest of the ecosystem — email, SMS, push, webhook, Slack, and in-app. A platform tells us an event happened; we decide, per team-configured rule, who gets told, on what channel, with what copy — and log the full journey from queue to delivery.
            </p>
            <div style="display:flex;gap:14px;flex-wrap:wrap;">
                @guest
                    <a href="{{ route('register') }}" class="btn-primary">Get started</a>
                    <a href="#features" class="btn-ghost">See how it works</a>
                @endguest
                @auth
                    <a href="{{ url('/dashboard') }}" class="btn-primary">Go to Dashboard</a>
                @endauth
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section id="features" style="padding:1rem max(1.5rem,5vw) 5rem;">
        <div class="wrap">
            <div style="text-align:center;max-width:640px;margin:0 auto 3rem;">
                <h2 style="font-size:2rem;font-weight:700;color:#f4f4f5;letter-spacing:-0.02em;margin-bottom:0.75rem;">One pipeline, every channel</h2>
                <p style="color:#a1a1aa;font-size:15px;">Channels, templates, rules, delivery logs, batches, and schedules — running behind team-scoped auth.</p>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1.25rem;">
                <div class="card">
                    <div class="card-icon">📬</div>
                    <h3 style="font-size:1rem;font-weight:700;color:#f4f4f5;margin-bottom:0.5rem;">Multi-Channel Delivery</h3>
                    <p style="font-size:13.5px;color:#a1a1aa;margin-bottom:0.75rem;">Configure delivery channels per team and route to whichever one fits the moment.</p>
                    <div>
                        <span class="chip">Email</span><span class="chip">SMS</span><span class="chip">Push</span><span class="chip">Webhook</span><span class="chip">Slack</span><span class="chip">In-app</span>
                    </div>
                </div>
                <div class="card">
                    <div class="card-icon">✍️</div>
                    <h3 style="font-size:1rem;font-weight:700;color:#f4f4f5;margin-bottom:0.5rem;">AI-Assisted Templates</h3>
                    <p style="font-size:13.5px;color:#a1a1aa;">Draft message templates with variable interpolation, then reuse them across every rule and channel.</p>
                </div>
                <div class="card">
                    <div class="card-icon">🔀</div>
                    <h3 style="font-size:1rem;font-weight:700;color:#f4f4f5;margin-bottom:0.5rem;">Rule-Based Routing</h3>
                    <p style="font-size:13.5px;color:#a1a1aa;">Map any trigger event to a template and a channel, with conditions — per team, not a global default.</p>
                </div>
                <div class="card">
                    <div class="card-icon">📊</div>
                    <h3 style="font-size:1rem;font-weight:700;color:#f4f4f5;margin-bottom:0.5rem;">Full Delivery Audit Trail</h3>
                    <p style="font-size:13.5px;color:#a1a1aa;">Every notification's journey — queued, sent, delivered, opened, clicked, or failed — logged per recipient.</p>
                </div>
                <div class="card">
                    <div class="card-icon">🔗</div>
                    <h3 style="font-size:1rem;font-weight:700;color:#f4f4f5;margin-bottom:0.5rem;">Inbound Webhooks</h3>
                    <p style="font-size:13.5px;color:#a1a1aa;">Signature-verified inbound endpoints translate a third-party event into one of your own trigger events.</p>
                </div>
                <div class="card">
                    <div class="card-icon">⏱️</div>
                    <h3 style="font-size:1rem;font-weight:700;color:#f4f4f5;margin-bottom:0.5rem;">Batches &amp; Schedules</h3>
                    <p style="font-size:13.5px;color:#a1a1aa;">Bulk sends with progress tracking, plus recurring sends tied to a cron schedule and timezone.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section style="padding:2rem max(1.5rem,5vw) 7rem;text-align:center;">
        <div class="wrap" style="max-width:600px;padding:3rem 2.5rem;background:#111820;border:1px solid rgba(56,189,248,0.18);border-radius:20px;">
            <h2 style="font-size:1.7rem;font-weight:700;color:#f4f4f5;letter-spacing:-0.02em;margin-bottom:0.75rem;">Stop guessing what got through</h2>
            <p style="font-size:14px;color:#a1a1aa;margin-bottom:2rem;">Route every event to the right channel, with copy your team controls, and a delivery log you can actually trust.</p>
            @guest
                <a href="{{ route('register') }}" class="btn-primary">Create your free account</a>
            @else
                <a href="{{ url('/dashboard') }}" class="btn-primary">Go to your Dashboard</a>
            @endguest
        </div>
    </section>

    {{-- Footer --}}
    <footer style="border-top:1px solid rgba(255,255,255,0.06);padding:2.5rem max(1.5rem,5vw);">
        <div class="wrap" style="display:flex;flex-direction:column;align-items:center;gap:1rem;text-align:center;">
            <img src="{{ asset('images/logo.png') }}" alt="Dot.Notify" style="height:30px;width:auto;opacity:0.9;">
            <p style="font-size:12px;color:#52525b;">&copy; {{ date('Y') }} Dot.Notify · The shared last mile of the Dot Ecosystem</p>
        </div>
    </footer>
</body>
</html>
