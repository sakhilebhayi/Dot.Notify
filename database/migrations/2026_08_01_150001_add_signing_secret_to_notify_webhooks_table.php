<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inbound webhook receiving pass (2026-08-01): `notify_webhooks` issued
 * tokens from day one but nothing ever verified a request against them.
 * `signing_secret` backs the HMAC-SHA256 signature scheme enforced by
 * App\Http\Controllers\WebhookInboundController — the token identifies
 * *which* webhook, the signature (computed over the raw request body with
 * this secret, sent as `X-Dot-Signature`, verified with `hash_equals()`)
 * proves the caller actually holds the secret and the body wasn't tampered
 * with in transit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notify_webhooks', function (Blueprint $table) {
            $table->string('signing_secret', 64)->nullable()->after('endpoint_token');
        });
    }

    public function down(): void
    {
        Schema::table('notify_webhooks', function (Blueprint $table) {
            $table->dropColumn('signing_secret');
        });
    }
};
