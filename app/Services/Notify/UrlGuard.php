<?php

namespace App\Services\Notify;

use Closure;

/**
 * Decides whether Notify may call a team-supplied URL (webhook and Slack
 * channels). Teams control these URLs, so without this a team admin could
 * aim a channel at the server's own network (cloud metadata at
 * 169.254.169.254, an internal admin panel, localhost).
 *
 * The host is resolved HERE and every address it resolves to must be public.
 * SafeHttp then pins the connection to the address that passed, so DNS cannot
 * answer differently between this check and the request (DNS rebinding).
 */
class UrlGuard
{
    /** CIDR ranges that are never public. Checked on top of PHP's own flags. */
    private const BLOCKED = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16',
        '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24', '192.168.0.0/16', '198.18.0.0/15',
        '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
        '::/128', '::1/128', 'fc00::/7', 'fe80::/10', 'ff00::/8', '2001:db8::/32',
    ];

    /** @var Closure(string): array<int, string> */
    private Closure $resolver;

    /** @param  (Closure(string): array<int, string>)|null  $resolver  host -> list of IPs (tests inject one) */
    public function __construct(?Closure $resolver = null)
    {
        $this->resolver = $resolver ?? self::dnsResolver(...);
    }

    /**
     * @return array{scheme: string, host: string, port: int, ip: string, ips: array<int, string>}
     *
     * @throws UnsafeUrl
     */
    public function assertSafe(string $url): array
    {
        $parts = parse_url(trim($url));

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new UnsafeUrl('URL is not a valid absolute http(s) URL.');
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new UnsafeUrl('Only http and https URLs are allowed.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafeUrl('URLs with embedded credentials are not allowed.');
        }

        $host = strtolower(trim($parts['host'], '[]'));
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } elseif (preg_match('/^(0x[0-9a-f]+|[0-9]+)(\.(0x[0-9a-f]+|[0-9]+))*$/i', $host)) {
            // 2130706433, 0x7f000001, 127.1: legacy spellings of an IPv4 address.
            throw new UnsafeUrl('Numeric host spelling is not allowed.');
        } else {
            $ips = array_values(array_unique(($this->resolver)($host)));
        }

        if ($ips === []) {
            throw new UnsafeUrl("Host {$host} did not resolve.");
        }

        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                throw new UnsafeUrl("Blocked: {$host} resolves to a private or reserved address.");
            }
        }

        return ['scheme' => $scheme, 'host' => $host, 'port' => $port, 'ip' => $ips[0], 'ips' => $ips];
    }

    public static function isPublicIp(string $ip): bool
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return false;
        }

        // ::ffff:a.b.c.d is just IPv4 in IPv6 clothing: judge the IPv4 part.
        if (strlen($packed) === 16 && str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")) {
            $ip = inet_ntop(substr($packed, 12));
            $packed = substr($packed, 12);
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        foreach (self::BLOCKED as $cidr) {
            if (self::inCidr($packed, $cidr)) {
                return false;
            }
        }

        return true;
    }

    private static function inCidr(string $packedIp, string $cidr): bool
    {
        [$network, $bits] = explode('/', $cidr);
        $packedNetwork = inet_pton($network);

        if (strlen($packedNetwork) !== strlen($packedIp)) {
            return false;
        }

        $bits = (int) $bits;
        $fullBytes = intdiv($bits, 8);

        if (substr($packedIp, 0, $fullBytes) !== substr($packedNetwork, 0, $fullBytes)) {
            return false;
        }

        $remaining = $bits % 8;
        if ($remaining === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remaining)) & 0xFF;

        return (ord($packedIp[$fullBytes]) & $mask) === (ord($packedNetwork[$fullBytes]) & $mask);
    }

    /** @return array<int, string> */
    private static function dnsResolver(string $host): array
    {
        $ips = gethostbynamel($host) ?: [];

        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return $ips;
    }
}
