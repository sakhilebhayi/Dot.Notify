<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Governance hardening pass (2026-08-11): notify_logs had no way to
 * attribute a row back to the NotifyRule that created it, which
 * NotifyRule::isOverRateCeiling() needs to count a rule's own recent
 * volume. Nullable — logs created outside the rule pipeline (manual
 * sends, NotifyBatch) simply have no rule to attribute to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notify_logs', function (Blueprint $table) {
            $table->foreignId('rule_id')->nullable()->after('template_id')->constrained('notify_rules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('notify_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rule_id');
        });
    }
};
