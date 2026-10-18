<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columns for the service ingest API and queued delivery.
 *
 * notify_logs: one row per notification already; now it also records how
 * delivery is going (attempts, last_error, next_retry_at, last_attempt_at),
 * its notification_type (for opt-outs) and `body`, the encrypted real text
 * kept only while body_preview had secrets redacted.
 *
 * notify_inbound_events: was webhook-only. Ingest events have no webhook, so
 * notify_webhook_id becomes nullable, and (team, platform, idempotency_key)
 * identifies a caller's event (the unique index is in the indexes migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notify_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('notify_logs', 'attempts')) {
                $table->unsignedSmallInteger('attempts')->default(0);
            }
            if (! Schema::hasColumn('notify_logs', 'last_error')) {
                $table->text('last_error')->nullable();
            }
            if (! Schema::hasColumn('notify_logs', 'next_retry_at')) {
                $table->timestamp('next_retry_at')->nullable();
            }
            if (! Schema::hasColumn('notify_logs', 'last_attempt_at')) {
                $table->timestamp('last_attempt_at')->nullable();
            }
            if (! Schema::hasColumn('notify_logs', 'notification_type')) {
                $table->string('notification_type', 40)->nullable();
            }
            if (! Schema::hasColumn('notify_logs', 'body')) {
                $table->text('body')->nullable();
            }
        });

        Schema::table('notify_inbound_events', function (Blueprint $table) {
            if (! Schema::hasColumn('notify_inbound_events', 'platform')) {
                $table->string('platform', 40)->nullable();
            }
            if (! Schema::hasColumn('notify_inbound_events', 'idempotency_key')) {
                $table->string('idempotency_key', 120)->nullable();
            }
        });

        Schema::table('notify_inbound_events', function (Blueprint $table) {
            $table->unsignedBigInteger('notify_webhook_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('notify_inbound_events', function (Blueprint $table) {
            $table->dropColumn(['platform', 'idempotency_key']);
        });

        Schema::table('notify_logs', function (Blueprint $table) {
            $table->dropColumn(['attempts', 'last_error', 'next_retry_at', 'last_attempt_at', 'notification_type', 'body']);
        });
    }
};
