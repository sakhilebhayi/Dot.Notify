<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the paths that scan these tables on every run or request:
 *  - notify_logs (status, next_retry_at): the sweeper's "what is due" scan,
 *    which used to read the whole table filtering status = 'queued'.
 *  - notify_logs (rule_id, created_at): NotifyRule::isOverRateCeiling().
 *  - notify_inbound_events (notify_webhook_id): webhook -> its events
 *    (Postgres and SQLite do not index a foreign key on their own).
 *  - notify_inbound_events unique (team_id, platform, idempotency_key): the
 *    database-level guarantee behind "a replay sends nothing". Webhook rows
 *    leave platform/key NULL, and NULLs never collide in a unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notify_logs', function (Blueprint $table) {
            if (! Schema::hasIndex('notify_logs', ['status', 'next_retry_at'])) {
                $table->index(['status', 'next_retry_at']);
            }
            if (! Schema::hasIndex('notify_logs', ['rule_id', 'created_at'])) {
                $table->index(['rule_id', 'created_at']);
            }
        });

        Schema::table('notify_inbound_events', function (Blueprint $table) {
            if (! Schema::hasIndex('notify_inbound_events', ['notify_webhook_id'])) {
                $table->index('notify_webhook_id');
            }
            if (! Schema::hasIndex('notify_inbound_events', ['team_id', 'platform', 'idempotency_key'], 'unique')) {
                $table->unique(['team_id', 'platform', 'idempotency_key']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('notify_inbound_events', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'platform', 'idempotency_key']);
            $table->dropIndex(['notify_webhook_id']);
        });

        Schema::table('notify_logs', function (Blueprint $table) {
            $table->dropIndex(['rule_id', 'created_at']);
            $table->dropIndex(['status', 'next_retry_at']);
        });
    }
};
