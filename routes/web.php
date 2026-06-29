<?php

use App\Http\Controllers\Auth\EcosystemAuthController;
use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\NotifyTemplate;
use Illuminate\Support\Facades\Route;

Route::get('/auth/ecosystem', [EcosystemAuthController::class, 'handle'])
    ->name('ecosystem.auth');

Route::get('/', fn () => view('welcome'));

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        $teamId = auth()->user()->currentTeam->id;

        return view('dashboard', [
            'activeChannels' => NotifyChannel::where('team_id', $teamId)->where('is_active', true)->count(),
            'sentToday'      => NotifyLog::where('team_id', $teamId)->whereDate('sent_at', today())->whereIn('status', ['sent', 'delivered', 'opened'])->count(),
            'failedToday'    => NotifyLog::where('team_id', $teamId)->whereDate('created_at', today())->where('status', 'failed')->count(),
            'templateCount'  => NotifyTemplate::where('team_id', $teamId)->count(),
        ]);
    })->name('dashboard');
});
