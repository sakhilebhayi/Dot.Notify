<?php

namespace App\Services\InfoDot;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asks InfoDot whether a company still holds this platform, and whether InfoDot has the platform switched on at all
 * (R-50, R-61).
 *
 * One question, about one company, answered from InfoDot's read endpoint with this platform's own service secret.
 * A clear answer is kept for one minute, so a switch made at InfoDot (a platform put in maintenance) takes effect
 * within about a minute, and is also remembered for six hours. Anything that is not a clear answer (InfoDot down,
 * slow, refusing, a body this app cannot read, no secret configured) is "unknown": the last clear answer under six
 * hours old is used, so a platform last told "unavailable" stays unavailable; with none, the caller carries on as if
 * the check did not exist. An outage at InfoDot never locks a company out that was last told it could work. A
 * failure is logged once per window.
 */
class EntitlementClient
{
    public const ANSWER_TTL = 60;

    public const FAILURE_TTL = 60;

    /** How long the last clear answer is used when InfoDot cannot be reached. */
    public const LAST_KNOWN_TTL = 21600;

    public function enabled(): bool
    {
        return (bool) config('ecosystem.infodot.entitlement_check', true);
    }

    /**
     * @return array{entitled: bool, status: string, available: bool, message: ?string, features: array<string, array{state: string, message: ?string}>}|null null when InfoDot gave no clear answer and none is remembered
     */
    public function forCompany(string $uuid): ?array
    {
        $key = 'infodot:entitlement:'.$this->platform().':'.$uuid;

        try {
            $cached = Cache::get($key);
        } catch (Throwable) {
            $cached = null;
        }

        if (is_array($cached) && array_key_exists('answer', $cached)) {
            return $cached['answer'];
        }

        $answer = $this->ask($uuid);

        try {
            if ($answer !== null) {
                Cache::put($key.':last', ['answer' => $answer, 'at' => time()], self::LAST_KNOWN_TTL);
            } else {
                $last = Cache::get($key.':last');
                $answer = is_array($last) && is_array($last['answer'] ?? null) && time() - (int) ($last['at'] ?? 0) < self::LAST_KNOWN_TTL ? $last['answer'] : null;
            }

            Cache::put($key, ['answer' => $answer], $answer === null ? self::FAILURE_TTL : self::ANSWER_TTL);
        } catch (Throwable) {
            // No cache, no memory of the answer: the next request asks again.
        }

        return $answer;
    }

    private function platform(): string
    {
        return (string) config('ecosystem.infodot.platform', 'notify');
    }

    /** InfoDot's read endpoint, on the same host this app redeems sign-in codes at. */
    private function url(string $uuid): ?string
    {
        $configured = config('ecosystem.infodot.entitlement_url');

        if (is_string($configured) && $configured !== '') {
            return str_replace('{uuid}', rawurlencode($uuid), $configured);
        }

        $redeem = parse_url((string) config('ecosystem.infodot.redeem_url', ''));

        if (! is_array($redeem) || empty($redeem['scheme']) || empty($redeem['host'])) {
            return null;
        }

        return $redeem['scheme'].'://'.$redeem['host'].(isset($redeem['port']) ? ':'.$redeem['port'] : '')
            .'/api/ecosystem/companies/'.rawurlencode($uuid).'/platforms';
    }

    /**
     * @return array{entitled: bool, status: string, available: bool, message: ?string, features: array<string, array{state: string, message: ?string}>}|null
     */
    private function ask(string $uuid): ?array
    {
        $bearer = config('ecosystem.infodot.secret');
        $url = $this->url($uuid);

        if (! is_string($bearer) || $bearer === '' || $url === null) {
            return $this->unknown($uuid, 'not configured');
        }

        try {
            $response = Http::withToken($bearer)
                ->acceptJson()
                ->withHeaders(['X-Dot-Platform' => $this->platform()])
                ->timeout((int) config('ecosystem.infodot.entitlement_timeout', 3))
                ->get($url, ['platform' => $this->platform()]);

            if (! $response->successful()) {
                return $this->unknown($uuid, 'status '.$response->status());
            }

            $body = $response->json();
        } catch (Throwable $e) {
            return $this->unknown($uuid, 'unreachable ('.class_basename($e).')');
        }

        $rows = is_array($body) ? ($body['platforms'] ?? null) : null;

        if (! is_array($rows) || ($body['company']['uuid'] ?? null) !== $uuid) {
            return $this->unknown($uuid, 'unreadable answer');
        }

        foreach ($rows as $row) {
            if (is_array($row) && ($row['key'] ?? null) === $this->platform()) {
                if (! is_bool($row['entitled'] ?? null)) {
                    return $this->unknown($uuid, 'unreadable answer');
                }

                return [
                    'entitled' => $row['entitled'],
                    'status' => is_string($row['status'] ?? null) ? $row['status'] : '',
                    // R-61: an InfoDot from before contract 2 gives no `available`, which is not a switch-off.
                    'available' => ($row['available'] ?? true) !== false,
                    'message' => is_string($row['message'] ?? null) && $row['message'] !== '' ? mb_substr($row['message'], 0, 400) : null,
                    'features' => self::features($row['features'] ?? null),
                ];
            }
        }

        return $this->unknown($uuid, 'own row missing');
    }

    /**
     * The feature switches in this platform's own row (R-61), kept as key => state and message. Anything malformed is
     * ignored, and a feature that is not listed is available.
     *
     * @return array<string, array{state: string, message: ?string}>
     */
    private static function features(mixed $raw): array
    {
        $out = [];

        foreach (is_array($raw) ? $raw : [] as $key => $row) {
            if (is_string($key) && is_array($row) && is_string($row['state'] ?? null)) {
                $out[$key] = ['state' => $row['state'], 'message' => is_string($row['message'] ?? null) && $row['message'] !== '' ? mb_substr($row['message'], 0, 400) : null];
            }
        }

        return $out;
    }

    /** Logged where the failure is stored, so once per cache window. Never the secret, never a person. */
    private function unknown(string $uuid, string $why): null
    {
        Log::warning('InfoDot entitlement check gave no answer; carrying on without it.', ['company' => $uuid, 'reason' => $why]);

        return null;
    }
}
