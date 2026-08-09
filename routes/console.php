<?php

use App\Console\Commands\DispatchQueuedNotifications;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─── Scheduled Platform Jobs ──────────────────────────────────────────────────
// This platform's first scheduled process — see
// docs/superpowers/specs/2026-08-09-queued-notification-dispatch-design.md.
// Every minute, not daily: a notification platform needs timely delivery,
// not a periodic health check.
Schedule::command(DispatchQueuedNotifications::class)
    ->everyMinute()
    ->withoutOverlapping();
