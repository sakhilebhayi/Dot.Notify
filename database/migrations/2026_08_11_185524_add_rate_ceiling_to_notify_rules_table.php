<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Governance hardening pass (2026-08-11), closing wiki.md §6's "per-class
 * rate ceilings on NotifyRule/NotifyBatch to cap runaway send volume (the
 * 2026-02 incident class of failure)" roadmap item. Nullable/opt-in: a
 * rule with no ceiling behaves exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notify_rules', function (Blueprint $table) {
            $table->unsignedInteger('rate_ceiling_per_hour')->nullable()->after('conditions');
        });
    }

    public function down(): void
    {
        Schema::table('notify_rules', function (Blueprint $table) {
            $table->dropColumn('rate_ceiling_per_hour');
        });
    }
};
