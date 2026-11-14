<?php

namespace App\Services\InfoDot;

use Dot\Ui\PlatformSwitcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asks InfoDot whether a company still holds this platform (R-50).
 *
 * One question, about one company, answered from InfoDot's read endpoint with this platform's own service secret.
 * The answer is kept for ten minutes per company; a failure for one minute. Anything that is not a clear answer
 * (InfoDot down, slow, refusing, a body this app cannot read, no secret configured) is "unknown", and unknown never
 * locks a company out: the caller carries on as if the check did not exist. A failure is logged once per window.
 */
class EntitlementClient
{
    public const ANSWER_TTL = 600;

    public const FAILURE_TTL = 60;

    public function enabled(): bool
    {
        return (bool) config('ecosystem.infodot.entitlement_check', true);
    }

    /**
     * @return array{entitled: bool, status: string}|null null when InfoDot gave no usable answer
     */
    public function forCompany(string $uuid): ?array
    {
        $key = 'infodot:entitlement:'.$this->platform().':'.$uuid;

        try {
            $cached = Cache::get($key);
        } catch (Throwable) {
            $cached = null;
        }

        if (is_array($cached)) {
            return isset($cached['entitled']) ? ['entitled' => (bool) $cached['entitled'], 'status' => (string) ($cached['status'] ?? '')] : null;
        }

        $answer = $this->ask($uuid);

        if (is_string($answer)) {
            Log::warning('InfoDot entitlement check gave no answer; carrying on without it.', ['company' => $uuid, 'reason' => $answer]);
            $this->remember($key, ['unknown' => true], self::FAILURE_TTL);

            return null;
        }

        $this->remember($key, $answer, self::ANSWER_TTL);

        return $answer;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function remember(string $key, array $value, int $seconds): void
    {
        try {
            Cache::put($key, $value, $seconds);
        } catch (Throwable) {
            // A cache that cannot be written only means the next request asks again.
        }
    }

    /**
     * @return array{entitled: bool, status: string}|string the answer, or why there is none
     */
    private function ask(string $uuid): array|string
    {
        $platform = $this->platform();

        if ($platform === '' || (string) config('ecosystem.infodot.secret') === '') {
            return 'not configured';
        }

        // The shared switcher reads the same endpoint for the header, and keeps the answer for the same ten minutes
        // (a failure for one minute). Asking it here makes the page one InfoDot call instead of two. Nothing about the
        // decision changes: held and not held are the two clear answers, and everything else (down, slow, refused, an
        // unreadable body, own row missing, a personal workspace) is unknown, which carries on, as before. Its read
        // timeout is the switcher's (3 s, connect 2 s).
        try {
            $answer = PlatformSwitcher::fromConfig($platform)->entitlement($uuid);
        } catch (Throwable $e) {
            return 'unreachable ('.class_basename($e).')';
        }

        if ($answer['state'] === PlatformSwitcher::HELD || $answer['state'] === PlatformSwitcher::NOT_HELD) {
            return ['entitled' => $answer['state'] === PlatformSwitcher::HELD, 'status' => is_string($answer['status']) ? $answer['status'] : ''];
        }

        return 'no usable answer';
    }

    private function platform(): string
    {
        return (string) config('ecosystem.infodot.platform');
    }
}
