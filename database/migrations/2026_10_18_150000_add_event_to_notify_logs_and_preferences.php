<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-event opt-outs for the event catalogue.
 *
 * notify_logs.event: which event produced the notification (null for logs not
 * made by the ingest API), so the send step can honour an opt-out of that event.
 *
 * notify_preferences.event: '' means the existing category-wide row
 * (notification_type + channel_type); a catalogued event name means "this person
 * switched this one event off on this channel". NOT NULL with '' rather than
 * NULL so the unique index still stops duplicates: NULLs never collide in a
 * unique index, empty strings do.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notify_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('notify_logs', 'event')) {
                $table->string('event', 150)->nullable();
            }
        });

        if (! Schema::hasColumn('notify_preferences', 'event')) {
            Schema::table('notify_preferences', function (Blueprint $table) {
                $table->string('event', 150)->default('');
            });

            Schema::table('notify_preferences', function (Blueprint $table) {
                $table->dropUnique(['user_id', 'notification_type', 'channel_type']);
                $table->unique(['user_id', 'notification_type', 'channel_type', 'event'], 'notify_preferences_unique_per_event');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('notify_preferences', 'event')) {
            // Event rows would collide with each other once the column is gone.
            DB::table('notify_preferences')->where('event', '!=', '')->delete();

            Schema::table('notify_preferences', function (Blueprint $table) {
                $table->dropUnique('notify_preferences_unique_per_event');
                $table->dropColumn('event');
            });

            Schema::table('notify_preferences', function (Blueprint $table) {
                $table->unique(['user_id', 'notification_type', 'channel_type']);
            });
        }

        Schema::table('notify_logs', function (Blueprint $table) {
            $table->dropColumn('event');
        });
    }
};
