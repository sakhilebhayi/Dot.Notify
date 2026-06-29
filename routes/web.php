<?php

use App\Http\Controllers\Auth\EcosystemAuthController;
use App\Models\AnalyticsAlert;
use App\Models\DataSource;
use App\Models\Recommendation;
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
        $team = auth()->user()->currentTeam;

        return view('dashboard', [
            'connectedCount'              => DataSource::where('team_id', $team->id)->where('status', 'connected')->count(),
            'openAlertCount'              => AnalyticsAlert::where('team_id', $team->id)->where('status', 'open')->count(),
            'pendingRecommendationCount'  => Recommendation::where('team_id', $team->id)->where('status', 'pending')->count(),
        ]);
    })->name('dashboard');
});
