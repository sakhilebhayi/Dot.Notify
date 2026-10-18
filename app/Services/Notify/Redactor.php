<?php

namespace App\Services\Notify;

/**
 * Keeps secrets out of stored payloads, previews and error text.
 *
 * Two ways a value is redacted: the caller marked its key in `sensitive`
 * (dot paths into data, e.g. "link" or "customer.ssn"), or the key itself
 * looks like a secret (password, token, api key...).
 */
class Redactor
{
    public const MASK = '[redacted]';

    private const SECRET_KEY = '/(pass(word|wd)?|secret|token|api[_-]?key|authorization|credential|private[_-]?key)/i';

    /**
     * @param  array<mixed>  $data
     * @param  array<int, string>  $sensitivePaths
     * @return array<mixed>
     */
    public static function payload(array $data, array $sensitivePaths = [], string $prefix = ''): array
    {
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (in_array($path, $sensitivePaths, true) || (is_string($key) && preg_match(self::SECRET_KEY, $key))) {
                $data[$key] = self::MASK;

                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::payload($value, $sensitivePaths, $path);
            }
        }

        return $data;
    }

    /**
     * Flatten nested data to "a.b" => "string" for {{ a.b }} template slots.
     *
     * @param  array<mixed>  $data
     * @return array<string, string>
     */
    public static function flatten(array $data, string $prefix = ''): array
    {
        $flat = [];

        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $flat += self::flatten($value, $path);
            } elseif (is_bool($value)) {
                $flat[$path] = $value ? 'true' : 'false';
            } elseif (is_scalar($value)) {
                $flat[$path] = (string) $value;
            }
        }

        return $flat;
    }

    /**
     * Error text from HTTP/mail libraries often embeds the full URL, and a
     * Slack webhook URL is itself the secret. Keep scheme + host, drop the rest.
     */
    public static function scrubError(string $message): string
    {
        $message = preg_replace_callback(
            '~\b(https?)://([^/\s\'"<>:?#]+)(:\d+)?[^\s\'"<>]*~i',
            fn (array $m) => $m[1].'://'.$m[2].($m[3] ?? '').'/…',
            $message,
        ) ?? $message;

        return mb_substr($message, 0, 500);
    }
}
