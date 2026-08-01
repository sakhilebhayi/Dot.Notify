<?php

use App\Http\Controllers\Auth\EcosystemAuthController;
use App\Http\Controllers\WebhookInboundController;
use App\Models\NotifyBatch;
use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\NotifyTemplate;
use Illuminate\Support\Facades\Route;

Route::get('/auth/ecosystem', [EcosystemAuthController::class, 'handle'])
    ->name('ecosystem.auth');

Route::get('/', fn () => view('welcome'));

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
        $teamId = auth()->user()->currentTeam->id;

        // Delivery success rate: share of the last 30 days' logs that reached a
        // successful terminal state, among logs that reached any terminal state
        // (delivered/opened/clicked vs failed/bounced). Logs still queued/sent
        // are excluded since they haven't resolved yet.
        $recentLogs   = NotifyLog::where('team_id', $teamId)->where('created_at', '>=', now()->subDays(30));
        $succeeded    = (clone $recentLogs)->whereIn('status', ['delivered', 'opened', 'clicked'])->count();
        $failed       = (clone $recentLogs)->whereIn('status', ['failed', 'bounced'])->count();
        $resolved     = $succeeded + $failed;

        return view('dashboard', [
            'activeChannels'      => NotifyChannel::where('team_id', $teamId)->where('is_active', true)->count(),
            'totalChannels'       => NotifyChannel::where('team_id', $teamId)->count(),
            'sentToday'           => NotifyLog::where('team_id', $teamId)->whereDate('sent_at', today())->whereIn('status', ['sent', 'delivered', 'opened'])->count(),
            'failedToday'         => NotifyLog::where('team_id', $teamId)->whereDate('created_at', today())->where('status', 'failed')->count(),
            'templateCount'       => NotifyTemplate::where('team_id', $teamId)->count(),
            'deliverySuccessRate' => $resolved > 0 ? round(($succeeded / $resolved) * 100, 1) : null,
            'recentBatches'       => NotifyBatch::where('team_id', $teamId)->orderByDesc('created_at')->limit(5)->get(),
        ]);
    })->name('dashboard');
});
