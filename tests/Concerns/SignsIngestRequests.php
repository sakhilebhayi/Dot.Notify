<?php

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;

/**
 * Signs and posts a request to /api/ingest/events the way a calling Dot app does:
 * HMAC-SHA256 over "{timestamp}.{raw body}" with the platform's own secret.
 */
trait SignsIngestRequests
{
    protected function signedIngest(string $platform, string $secret, array $body): TestResponse
    {
        $raw = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = now()->timestamp;

        return $this->call('POST', '/api/ingest/events', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_INFODOT_TIMESTAMP' => $timestamp,
            'HTTP_X_INFODOT_SIGNATURE' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$raw, $secret),
        ], $raw);
    }
}
