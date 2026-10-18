<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Encrypted values are base64 text, not JSON and not 64 characters, so the
 * two secret-bearing columns become plain text. The values are encrypted by
 * the next migration; this one only changes the column type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notify_channels', function (Blueprint $table) {
            $table->text('config')->nullable()->change();
        });

        Schema::table('notify_webhooks', function (Blueprint $table) {
            $table->text('signing_secret')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Intentionally empty: once encrypted, the values would not fit the old
        // json / string(64) columns. Restore from backup if you must go back.
    }
};
