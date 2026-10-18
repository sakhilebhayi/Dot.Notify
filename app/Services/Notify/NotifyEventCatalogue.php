<?php

namespace App\Services\Notify;

use InvalidArgumentException;

/**
 * Read access to config/notify_events.php, the one list of events the Dot apps
 * send to the ingest API, and the filter that keeps only what the catalogue
 * allows of an event's `data`.
 *
 * validate()/inspect() keep, per event: only the allowed keys; only scalar
 * values (a nested array under an allowed key is refused with
 * InvalidEventData); strings with control characters flattened to spaces and
 * cut to max_value_length; and URL keys only when they are absolute https URLs
 * on the calling platform's own registry host. Everything else is dropped.
 */
class NotifyEventCatalogue
{
    public function version(): int
    {
        return (int) config('notify_events.version', 1);
    }

    /**
     * @return array<string, array{key: string, name: string, events: array<string, array<string, mixed>>}> keyed by platform key
     */
    public function platforms(): array
    {
        $platforms = [];

        foreach ((array) config('notify_events.platforms', []) as $key => $events) {
            $platforms[$key] = [
                'key' => $key,
                'name' => (string) (config("ecosystem.platforms.{$key}.name") ?? ucfirst($key)),
                'events' => collect($events)
                    ->mapWithKeys(fn (array $definition, string $event) => [$event => $this->normalise($key, $event, $definition)])
                    ->all(),
            ];
        }

        return $platforms;
    }

    /**
     * Every catalogued event, keyed by event name.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $all = [];

        foreach ($this->platforms() as $platform) {
            $all += $platform['events'];
        }

        return $all;
    }

    /**
     * @return array<string, mixed>|null null when the event is unknown, or belongs to a different platform than the one given
     */
    public function find(string $event, ?string $platform = null): ?array
    {
        $found = $this->all()[$event] ?? null;

        if ($found === null || ($platform !== null && $found['platform'] !== $platform)) {
            return null;
        }

        return $found;
    }

    /** The platform key that owns the event, or null when it is not catalogued. */
    public function owner(string $event): ?string
    {
        return $this->find($event)['platform'] ?? null;
    }

    /** @return array<int, string> */
    public function allowedKeys(string $event): array
    {
        return $this->find($event)['data'] ?? [];
    }

    /** @return array{name: string, subject: string, body: string, variables: array<int, string>} */
    public function defaultTemplate(string $event): array
    {
        $definition = $this->find($event) ?? throw new InvalidArgumentException("Unknown event {$event}.");

        return [
            'name' => $definition['platform_name'].': '.$definition['label'],
            'subject' => $definition['template']['subject'],
            'body' => $definition['template']['body'],
            'variables' => $definition['data'],
        ];
    }

    /**
     * @return array<int, string> the {{ variable }} names a template text uses
     */
    public function templateVariables(string $text): array
    {
        preg_match_all('/\{\{\s*([^{}\s]+)\s*\}\}/', $text, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> the sanitised data
     *
     * @throws InvalidEventData|InvalidArgumentException
     */
    public function validate(string $platform, string $event, array $data): array
    {
        return $this->inspect($platform, $event, $data)['data'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{data: array<string, mixed>, dropped: array<int, string>} the kept data, and the names of the keys that were dropped
     *
     * @throws InvalidEventData|InvalidArgumentException
     */
    public function inspect(string $platform, string $event, array $data): array
    {
        $definition = $this->find($event, $platform) ?? throw new InvalidArgumentException("Event {$event} is not catalogued for {$platform}.");

        $allowed = array_flip($definition['data']);
        $kept = [];
        $dropped = [];
        $errors = [];

        foreach ($data as $key => $value) {
            $key = (string) $key;

            if (! isset($allowed[$key])) {
                $dropped[] = $key;

                continue;
            }

            if (is_array($value)) {
                $errors["data.{$key}"] = ['This field must be a single value, not a list or an object.'];

                continue;
            }

            $clean = $this->isUrlKey($key) ? $this->cleanUrl($platform, $value) : $this->cleanValue($value);

            if ($clean === null) {
                // null and empty are the same as not sent; anything else was refused.
                if ($value !== null && $value !== '') {
                    $dropped[] = $key;
                }

                continue;
            }

            $kept[$key] = $clean;
        }

        if ($errors !== []) {
            throw new InvalidEventData($errors);
        }

        return ['data' => $kept, 'dropped' => $dropped];
    }

    /** True for an absolute https URL on the platform's own registry host and nothing else. */
    public function isAllowedUrl(string $platform, mixed $value): bool
    {
        if (! is_string($value) || $value === '' || strlen($value) > (int) config('notify_events.max_url_length', 2048)) {
            return false;
        }

        // Whitespace, control characters and backslashes are how URL parsers get confused.
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $value) === 1 || preg_match('~^https://~i', $value) !== 1) {
            return false;
        }

        $parts = parse_url($value);

        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        if (isset($parts['port']) && (int) $parts['port'] !== 443) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $allowed = $this->platformHost($platform);

        return $host !== '' && $allowed !== null && $host === $allowed;
    }

    private function platformHost(string $platform): ?string
    {
        $url = config("ecosystem.platforms.{$platform}.url");
        $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }

    private function isUrlKey(string $key): bool
    {
        return $key === 'url' || str_ends_with($key, '_url');
    }

    private function cleanUrl(string $platform, mixed $value): ?string
    {
        return $this->isAllowedUrl($platform, $value) ? $value : null;
    }

    private function cleanValue(mixed $value): string|int|float|bool|null
    {
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8')) {
            return null;
        }

        $text = trim((string) preg_replace('/ {2,}/', ' ', (string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value)));
        $max = max(2, (int) config('notify_events.max_value_length', 500));

        if (mb_strlen($text) > $max) {
            $text = mb_substr($text, 0, $max - 1).'…';
        }

        return $text === '' ? null : $text;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function normalise(string $platform, string $event, array $definition): array
    {
        return [
            'platform' => $platform,
            'platform_name' => (string) (config("ecosystem.platforms.{$platform}.name") ?? ucfirst($platform)),
            'event' => $event,
            'label' => (string) $definition['label'],
            'description' => (string) $definition['description'],
            'channel' => (string) ($definition['channel'] ?? 'email'),
            'recipient' => (string) ($definition['recipient'] ?? 'user'),
            'notification_type' => (string) ($definition['notification_type'] ?? 'transactional'),
            'template' => [
                'subject' => (string) $definition['template']['subject'],
                'body' => (string) $definition['template']['body'],
            ],
            'data' => array_values($definition['data']),
        ];
    }
}
