<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring-dispatch pass (2026-08-11): notify_schedules existed since the
 * first migration but nothing read or wrote it (README/wiki.md §6). A
 * schedule fires on a cron timer, not a domain event, so unlike an
 * event-triggered NotifyRule it can't source its recipient list from a
 * triggering payload — it needs its own explicit list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notify_schedules', function (Blueprint $table) {
            $table->json('recipients')->nullable()->after('cron_expression');
        });
    }

    public function down(): void
    {
        Schema::table('notify_schedules', function (Blueprint $table) {
            $table->dropColumn('recipients');
        });
    }
};
