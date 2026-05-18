<?php

declare(strict_types=1);

namespace App\Core\Security;

/**
 * SSRF protection helpers. Reject URLs that target loopback, link-local,
 * private (RFC1918), and CGNAT ranges, plus any scheme that isn't http/https.
 *
 * Usage: call `assertSafe($url)` before initiating an outbound HTTP request.
 * For cURL-based callers, also call `assertSafe()` again after each redirect,
 * or disable `CURLOPT_FOLLOWLOCATION` and follow manually so each hop is
 * validated.
 */
final class SsrfGuard
{
    /**
     * Strict guard: scheme must be http/https and every IP the host resolves
     * to must be a globally-routable unicast address.
     *
     * @throws SsrfException
     */
    public static function assertSafe(string $url): void
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw new SsrfException('URL is malformed');
        }

        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new SsrfException('Only http/https URLs are allowed');
        }

        $host = $parts['host'];

        // Strip surrounding brackets for IPv6 literals.
        if (strlen($host) > 1 && $host[0] === '[' && substr($host, -1) === ']') {
            $host = substr($host, 1, -1);
        }

        // If it's an IP literal, check directly.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            self::assertPublicIp($host);
            return;
        }

        // Hostname — resolve to every A/AAAA and check each.
        $ips = self::resolveAll($host);
        if (empty($ips)) {
            throw new SsrfException('Could not resolve host');
        }
        foreach ($ips as $ip) {
            self::assertPublicIp($ip);
        }
    }

    /**
     * @throws SsrfException
     */
    private static function assertPublicIp(string $ip): void
    {
        $isValid = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
        if ($isValid !== false) {
            return;
        }
        throw new SsrfException('Target IP is not routable from the public internet: ' . $ip);
    }

    /**
     * Resolve hostname to all A and AAAA records. Returns the list of unique
     * IPs (empty if resolution failed entirely).
     *
     * @return string[]
     */
    private static function resolveAll(string $host): array
    {
        $ips = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $rec) {
                if (!empty($rec['ip'])) {
                    $ips[] = $rec['ip'];
                }
                if (!empty($rec['ipv6'])) {
                    $ips[] = $rec['ipv6'];
                }
            }
        }
        if (empty($ips)) {
            $byName = @gethostbynamel($host);
            if (is_array($byName)) {
                $ips = $byName;
            }
        }
        return array_values(array_unique($ips));
    }
}
