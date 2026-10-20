<?php

use App\Http\Controllers\Auth\EcosystemAuthController;
use App\Http\Controllers\RealtimeHealthController;
use App\Http\Controllers\WebhookInboundController;
use App\Models\NotifyBatch;
use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\NotifyTemplate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Jetstream\Jetstream;

Route::match(['get', 'post'], '/auth/ecosystem', [EcosystemAuthController::class, 'handle'])
    ->withoutMiddleware(['Illuminate\Foundation\Http\Middleware\VerifyCsrfToken', 'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken'])
    ->middleware('throttle:10,1')
    ->name('ecosystem.auth');

Route::get('/', fn () => view('welcome'));

// Cookie Policy — Jetstream's termsAndPrivacyPolicy feature covers terms.show/policy.show
// natively (registered at /terms-of-service and /privacy-policy, reading resources/markdown/
// terms.md and policy.md). There's no Jetstream equivalent for a Cookie Policy, so this one is
// wired by hand, following the exact same Markdown-source convention.
Route::get('/cookies', function () {
    return view('cookies', [
        'cookies' => Str::markdown(file_get_contents(Jetstream::localizedMarkdownPath('cookies.md'))),
    ]);
})->name('cookies');

// Inbound webhook receiver — deliberately outside the auth:sanctum/session
// group below. Callers are external systems (Stripe, GitHub, etc.), not
// logged-in users; they authenticate via the {token} + X-Dot-Signature
// HMAC scheme inside WebhookInboundController, not Sanctum/session auth.
// Rate-limited since it's unauthenticated-by-design and a target for abuse.
Route::post('/webhooks/{token}', [WebhookInboundController::class, 'receive'])
    ->middleware('throttle:30,1')
    ->name('webhooks.receive');

// Real-time infrastructure health check -- unauthenticated like Laravel's
// own /up, for uptime monitors/load balancers.
Route::get('/up/realtime', [RealtimeHealthController::class, 'check'])
    ->name('health.realtime');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        // currentTeam is null for a user with no team (freshly registered
        // and never created/joined one, or removed from their last team).
        // HasTeamScope's global scope only filters by team_id when
        // Auth::user()->currentTeam is present — with no current team it
        // is a deliberate no-op (see HasTeamScope's docblock), so an
        // unguarded render here wouldn't just be a null-deref, it would
        // silently return every team's NotifyChannel/NotifyLog/
        // NotifyTemplate/NotifyBatch rows. Guard before running any query.
        if (! Auth::user()?->currentTeam) {
            return redirect()->route('teams.create');
        }

        // No explicit team_id filter needed: NotifyChannel/NotifyLog/
        // NotifyTemplate/NotifyBatch's HasTeamScope trait applies it
        // automatically to every query against these models.

        return view('dashboard', [
            'activeChannels' => NotifyChannel::where('is_active', true)->count(),
            'totalChannels' => NotifyChannel::count(),
            // Only statuses something really produces: sent (the channel accepted
            // it), failed, suppressed (opt-out) and retrying. Nothing reports
            // delivery, opens, clicks or bounces back, so none are shown.
            'sentToday' => NotifyLog::whereDate('sent_at', today())->where('status', 'sent')->count(),
            'failedToday' => NotifyLog::whereDate('created_at', today())->where('status', 'failed')->count(),
            'retrying' => NotifyLog::where('status', 'retrying')->count(),
            'suppressed30d' => NotifyLog::where('status', 'suppressed')->where('created_at', '>=', now()->subDays(30))->count(),
            'templateCount' => NotifyTemplate::count(),
            'recentBatches' => NotifyBatch::orderByDesc('created_at')->limit(5)->get(),
        ]);
    })->name('dashboard');

    // Which events each Dot app can send, and which of them this company has switched on.
    Route::get('/connected-apps', function () {
        // Same guard as the dashboard: with no current team the team scope is a no-op.
        if (! Auth::user()?->currentTeam) {
            return redirect()->route('teams.create');
        }

        return view('connected-apps');
    })->name('connected-apps');
});
