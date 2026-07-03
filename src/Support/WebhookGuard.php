<?php

namespace Maestrodimateo\Workflow\Support;

use Maestrodimateo\Workflow\Exceptions\UnsafeWebhookUrlException;

/**
 * SSRF guard for outbound webhook URLs.
 *
 * Validates the scheme, an optional host allow-list, and — unless disabled —
 * that the host does not resolve to a private, loopback, link-local or
 * otherwise reserved address (which would let an attacker reach internal
 * services or the cloud metadata endpoint at 169.254.169.254).
 */
class WebhookGuard
{
    /**
     * @throws UnsafeWebhookUrlException When the URL is not safe to call.
     */
    public static function assertAllowed(string $url): void
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host'])) {
            throw new UnsafeWebhookUrlException('Invalid webhook URL.');
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $allowedSchemes = (array) config('workflow.webhook.allowed_schemes', ['https']);

        if (! in_array($scheme, $allowedSchemes, true)) {
            throw new UnsafeWebhookUrlException("Webhook scheme [{$scheme}] is not allowed.");
        }

        $host = trim($parts['host'], '[]');

        $allowedHosts = (array) config('workflow.webhook.allowed_hosts', []);
        if ($allowedHosts !== [] && ! in_array($host, $allowedHosts, true)) {
            throw new UnsafeWebhookUrlException("Webhook host [{$host}] is not in the allow-list.");
        }

        if (! config('workflow.webhook.block_private_ranges', true)) {
            return;
        }

        foreach (static::resolveIps($host) as $ip) {
            if (! static::isPublicIp($ip)) {
                throw new UnsafeWebhookUrlException("Webhook host [{$host}] resolves to a non-public address.");
            }
        }
    }

    /**
     * Resolve a host to the list of IP addresses it points to.
     *
     * @return array<int, string>
     *
     * @throws UnsafeWebhookUrlException When the host cannot be resolved.
     */
    private static function resolveIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = gethostbynamel($host) ?: [];

        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (! empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        if ($ips === []) {
            throw new UnsafeWebhookUrlException("Unable to resolve webhook host [{$host}].");
        }

        return $ips;
    }

    /**
     * A public IP is one that is neither private nor in a reserved range
     * (this excludes 127.0.0.0/8, 169.254.0.0/16, 10/8, 172.16/12, 192.168/16, ::1, …).
     */
    private static function isPublicIp(string $ip): bool
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
