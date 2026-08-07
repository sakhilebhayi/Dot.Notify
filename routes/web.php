<?php

use App\Http\Controllers\Auth\EcosystemAuthController;
use App\Http\Controllers\WebhookInboundController;
use App\Models\NotifyBatch;
use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\NotifyTemplate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Jetstream\Jetstream;

Route::get('/auth/ecosystem', [EcosystemAuthController::class, 'handle'])
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

        // Delivery success rate: share of the last 30 days' logs that reached a
        // successful terminal state, among logs that reached any terminal state
        // (delivered/opened/clicked vs failed/bounced). Logs still queued/sent
        // are excluded since they haven't resolved yet.
        $recentLogs = NotifyLog::where('created_at', '>=', now()->subDays(30));
        $succeeded = (clone $recentLogs)->whereIn('status', ['delivered', 'opened', 'clicked'])->count();
        $failed = (clone $recentLogs)->whereIn('status', ['failed', 'bounced'])->count();
        $resolved = $succeeded + $failed;

        return view('dashboard', [
            'activeChannels' => NotifyChannel::where('is_active', true)->count(),
            'totalChannels' => NotifyChannel::count(),
            'sentToday' => NotifyLog::whereDate('sent_at', today())->whereIn('status', ['sent', 'delivered', 'opened'])->count(),
            'failedToday' => NotifyLog::whereDate('created_at', today())->where('status', 'failed')->count(),
            'templateCount' => NotifyTemplate::count(),
            'deliverySuccessRate' => $resolved > 0 ? round(($succeeded / $resolved) * 100, 1) : null,
            'recentBatches' => NotifyBatch::orderByDesc('created_at')->limit(5)->get(),
        ]);
    })->name('dashboard');
});
