<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records every inbound webhook request that passed signature verification.
 * Deliberately does NOT record rejected requests (bad signature, missing
 * signature, unknown token) — that would let an attacker use response
 * timing/content plus a later admin UI to distinguish "this token exists
 * but the signature was wrong" from "this token doesn't exist", which is
 * exactly the enumeration leak App\Http\Controllers\WebhookInboundController
 * is written to avoid at the HTTP layer already.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('notify_inbound_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('notify_webhook_id')->constrained('notify_webhooks')->cascadeOnDelete();
            $table->foreignId('notify_log_id')->nullable()->constrained('notify_logs')->nullOnDelete();
            $table->json('payload');
            $table->string('source_event')->nullable(); // raw event name/type as sent by the third party
            $table->string('trigger_event')->nullable(); // resolved via NotifyWebhook::event_map
            $table->timestamp('verified_at'); // signature verification passed at this time
            $table->string('status')->default('received'); // received, routed, ignored, failed
            $table->text('note')->nullable(); // why it was ignored/failed, when applicable
            $table->timestamps();
            $table->index(['team_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notify_inbound_events');
    }
};
