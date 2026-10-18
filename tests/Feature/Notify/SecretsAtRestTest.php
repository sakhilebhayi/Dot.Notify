<?php

namespace Tests\Feature\Notify;

use App\Models\NotifyChannel;
use App\Models\NotifyWebhook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecretsAtRestTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_18_120200_encrypt_notify_secrets_at_rest.php');
    }

    public function test_channel_config_round_trips_but_is_never_stored_in_plaintext(): void
    {
        $team = User::factory()->withPersonalTeam()->create()->currentTeam;
        $config = ['host' => 'smtp.example.test', 'password' => 'hunter2-smtp', 'webhook_url' => 'https://hooks.slack.test/services/T/B/TOKEN'];

        $channel = NotifyChannel::create(['team_id' => $team->id, 'type' => 'email', 'name' => 'SMTP', 'config' => $config]);

        $raw = DB::table('notify_channels')->where('id', $channel->id)->value('config');
        $this->assertStringNotContainsString('hunter2-smtp', $raw);
        $this->assertStringNotContainsString('hooks.slack.test', $raw);
        $this->assertSame($config, NotifyChannel::withoutGlobalScopes()->find($channel->id)->config);
    }

    public function test_a_channel_without_config_stays_null(): void
    {
        $team = User::factory()->withPersonalTeam()->create()->currentTeam;
        $channel = NotifyChannel::create(['team_id' => $team->id, 'type' => 'in_app', 'name' => 'Bell']);

        $this->assertNull(DB::table('notify_channels')->where('id', $channel->id)->value('config'));
        $this->assertNull($channel->fresh()->config);
    }

    public function test_the_webhook_signing_secret_round_trips_but_is_never_stored_in_plaintext(): void
    {
        $team = User::factory()->withPersonalTeam()->create()->currentTeam;
        $webhook = NotifyWebhook::create(['team_id' => $team->id, 'name' => 'Stripe', 'signing_secret' => 'whsec_plain_value']);

        $raw = DB::table('notify_webhooks')->where('id', $webhook->id)->value('signing_secret');
        $this->assertStringNotContainsString('whsec_plain_value', $raw);
        $this->assertSame('whsec_plain_value', NotifyWebhook::withoutGlobalScopes()->find($webhook->id)->signing_secret);
        // Auto-generated secrets (64 random characters) must fit the column once encrypted.
        $auto = NotifyWebhook::create(['team_id' => $team->id, 'name' => 'Auto']);
        $this->assertSame(64, strlen(NotifyWebhook::withoutGlobalScopes()->find($auto->id)->signing_secret));
    }

    public function test_the_data_migration_encrypts_existing_plaintext_rows_and_is_idempotent(): void
    {
        $team = User::factory()->withPersonalTeam()->create()->currentTeam;
        $now = now();

        // Rows exactly as they were written before this change: plaintext JSON / plaintext secret.
        $plainChannel = DB::table('notify_channels')->insertGetId(['team_id' => $team->id, 'type' => 'slack', 'name' => 'Old', 'config' => json_encode(['webhook_url' => 'https://hooks.slack.test/OLD']), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $nullChannel = DB::table('notify_channels')->insertGetId(['team_id' => $team->id, 'type' => 'in_app', 'name' => 'Bell', 'config' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $alreadyEncrypted = DB::table('notify_channels')->insertGetId(['team_id' => $team->id, 'type' => 'slack', 'name' => 'New', 'config' => Crypt::encryptString(json_encode(['webhook_url' => 'https://hooks.slack.test/NEW'])), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $plainWebhook = DB::table('notify_webhooks')->insertGetId(['team_id' => $team->id, 'name' => 'Old hook', 'endpoint_token' => 'tok-old', 'signing_secret' => 'plain-old-secret', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $nullWebhook = DB::table('notify_webhooks')->insertGetId(['team_id' => $team->id, 'name' => 'No secret', 'endpoint_token' => 'tok-null', 'signing_secret' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);

        $this->migration()->up();

        $rawPlain = DB::table('notify_channels')->where('id', $plainChannel)->value('config');
        $this->assertStringNotContainsString('OLD', $rawPlain);
        $this->assertSame(['webhook_url' => 'https://hooks.slack.test/OLD'], NotifyChannel::withoutGlobalScopes()->find($plainChannel)->config);
        $this->assertNull(DB::table('notify_channels')->where('id', $nullChannel)->value('config'));
        $this->assertSame(['webhook_url' => 'https://hooks.slack.test/NEW'], NotifyChannel::withoutGlobalScopes()->find($alreadyEncrypted)->config);
        $this->assertSame('plain-old-secret', NotifyWebhook::withoutGlobalScopes()->find($plainWebhook)->signing_secret);
        $this->assertStringNotContainsString('plain-old-secret', DB::table('notify_webhooks')->where('id', $plainWebhook)->value('signing_secret'));
        $this->assertNull(DB::table('notify_webhooks')->where('id', $nullWebhook)->value('signing_secret'));

        $snapshot = [
            DB::table('notify_channels')->orderBy('id')->pluck('config', 'id')->all(),
            DB::table('notify_webhooks')->orderBy('id')->pluck('signing_secret', 'id')->all(),
        ];

        $this->migration()->up();

        $this->assertSame($snapshot, [
            DB::table('notify_channels')->orderBy('id')->pluck('config', 'id')->all(),
            DB::table('notify_webhooks')->orderBy('id')->pluck('signing_secret', 'id')->all(),
        ], 'Running the migration again must not double-encrypt anything.');
    }
}
