<?php

/*
|--------------------------------------------------------------------------
| Dot.Notify service settings
|--------------------------------------------------------------------------
*/

// Platform keys come from the shared ecosystem registry, so a new Dot app
// only needs NOTIFY_INGEST_SECRET_<KEY> in .env to be able to call.
$platformKeys = array_keys((require __DIR__.'/ecosystem.php')['platforms']);

return [

    'ingest' => [
        /*
        | POST /api/ingest/events is server to server. The CALLER signs
        | "{timestamp}.{raw body}" with HMAC-SHA256 using its own platform
        | secret; Notify verifies. A blank secret means that platform cannot
        | call at all. Generate with: php -r 'echo bin2hex(random_bytes(32));'
        */
        'secrets' => collect($platformKeys)
            ->mapWithKeys(fn (string $key) => [$key => (string) env('NOTIFY_INGEST_SECRET_'.strtoupper($key), '')])
            ->all(),

        // Seconds a signed timestamp stays valid either side of "now".
        'tolerance' => 300,

        // Accepted events per platform + company per minute.
        'rate_limit_per_minute' => (int) env('NOTIFY_INGEST_RATE_LIMIT', 120),

        'max_body_bytes' => 65536,
    ],

    /*
    | A daily aggregate snapshot (counts from the delivery log, per company) is published to Dot.Analytics'
    | server-to-server ingest (POST /api/ingest/snapshots, queued). Both the URL and the secret must be set or
    | nothing is sent. The secret is this platform's ANALYTICS_INGEST_SECRET_NOTIFY value from Dot.Analytics'
    | .env. The breaker stops calling an Analytics that fails `breaker_threshold` times in a row, for
    | `breaker_cooldown` seconds.
    */
    'analytics' => [
        'url' => env('ANALYTICS_INGEST_URL'),
        'secret' => env('ANALYTICS_INGEST_SECRET'),
        'platform' => env('ANALYTICS_PLATFORM_KEY', 'notify'),
        'timeout' => 5,
        'breaker_threshold' => 5,
        'breaker_cooldown' => 300,
    ],

    'delivery' => [
        // Total sends tried per notification (first try included).
        'max_attempts' => 5,

        // notify:dispatch-queued enqueues at most this many due logs per run.
        'sweep_batch' => 100,

        // A row stuck in "sending" this long means a worker died mid-send.
        'stale_sending_minutes' => 15,

        'http_timeout' => 5,
    ],

];
