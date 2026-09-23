<?php

use App\Console\Commands\DispatchDueSchedules;
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

// NotifySchedule's cron_expression is evaluated to-the-minute (see
// NotifySchedule::isDueAt()), so this also has to run every minute, not on
// its own schedule — a schedule due at 09:00 only matches during the 09:00
// minute.
Schedule::command(DispatchDueSchedules::class)
    ->everyMinute()
    ->withoutOverlapping();
