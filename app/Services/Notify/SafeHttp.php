<?php

namespace App\Services\Notify;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The only way channels call team-supplied URLs: guarded by UrlGuard, 5 second
 * timeout, redirects never followed (a public URL could otherwise bounce us to
 * an internal one), connection pinned to the address UrlGuard approved, and
 * the body signed when the channel has a secret.
 */
class SafeHttp
{
    public function __construct(private readonly UrlGuard $guard) {}

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws UnsafeUrl
     */
    public function postJson(string $url, array $payload, ?string $signingSecret = null): Response
    {
        $target = $this->guard->assertSafe($url);
        $timeout = (int) config('notify.delivery.http_timeout', 5);

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $headers = [];
        if ($signingSecret !== null && $signingSecret !== '') {
            $timestamp = (string) now()->timestamp;
            $headers['X-Dot-Timestamp'] = $timestamp;
            $headers['X-Dot-Signature'] = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $signingSecret);
        }

        $options = [];
        if (! filter_var($target['host'], FILTER_VALIDATE_IP) && defined('CURLOPT_RESOLVE')) {
            $options['curl'] = [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$target['ip']}"]];
        }

        return Http::timeout($timeout)
            ->connectTimeout($timeout)
            ->withoutRedirecting()
            ->withOptions($options)
            ->withHeaders($headers)
            ->withBody($body, 'application/json')
            ->post($url);
    }
}
