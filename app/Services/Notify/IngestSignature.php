<?php

namespace App\Services\Notify;

use Illuminate\Http\Request;

/**
 * Is this server to server call really from the platform it names, and fresh?
 *
 * Same scheme as InfoDot's other signed calls (Dot.Files' SignedRequest), with
 * the direction reversed: here the CALLER signs and Notify verifies.
 * Signature = HMAC-SHA256 over "{timestamp}.{raw body}" with the calling
 * platform's own secret, sent as "sha256=<hex>" in X-InfoDot-Signature, with the
 * unix time in X-InfoDot-Timestamp. The timestamp must be within five minutes
 * either way, so a captured request goes stale; the body cannot be swapped
 * under a signature; and one platform's secret cannot sign for another.
 */
class IngestSignature
{
    public static function valid(Request $request, string $secret): bool
    {
        $timestamp = (int) $request->header('X-InfoDot-Timestamp');
        $signature = (string) $request->header('X-InfoDot-Signature');

        // Always do the HMAC, even for an unconfigured platform, so a blank-secret
        // platform costs the same as a wrong signature.
        $key = $secret !== '' ? $secret : hash('sha256', 'notify-ingest-unconfigured-platform');
        $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $key);

        $fresh = $timestamp !== 0 && abs(now()->timestamp - $timestamp) <= (int) config('notify.ingest.tolerance', 300);

        // Callers may send the bare hex digest; accept it as well as "sha256=<hex>".
        $given = str_starts_with($signature, 'sha256=') ? $signature : 'sha256='.$signature;

        return hash_equals($expected, $given) && $fresh && $secret !== '';
    }
}
