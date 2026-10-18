<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Encrypts what was stored in plaintext: notify_channels.config (SMTP
 * credentials, Slack/webhook URLs) and notify_webhooks.signing_secret.
 *
 * Idempotent: a value that already decrypts with this app's key is left
 * alone, so re-running (or running after some rows were saved through the
 * new encrypted casts) never double-encrypts. Nulls stay null.
 *
 * Needs APP_KEY. Rotating APP_KEY later makes these unreadable, so rotate
 * with the usual re-encrypt step, not by just changing the key.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->encryptColumn('notify_channels', 'config');
        $this->encryptColumn('notify_webhooks', 'signing_secret');
    }

    public function down(): void
    {
        // Intentionally a no-op: decrypting back to plaintext on rollback would
        // quietly undo the security fix.
    }

    private function encryptColumn(string $table, string $column): void
    {
        DB::table($table)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table, $column) {
                foreach ($rows as $row) {
                    $value = (string) $row->{$column};

                    if ($this->isEncrypted($value)) {
                        continue;
                    }

                    DB::table($table)->where('id', $row->id)->update([$column => Crypt::encryptString($value)]);
                }
            });
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
