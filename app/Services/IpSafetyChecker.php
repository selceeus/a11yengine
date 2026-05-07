<?php

namespace App\Services;

class IpSafetyChecker
{
    /**
     * Returns true when every IP the hostname resolves to is public/routable.
     * Returns false (unsafe) when any resolved IP falls within a private,
     * loopback, link-local, or multicast range — or when the hostname cannot
     * be resolved at all.
     *
     * Pass $errorMessage by reference to receive a human-readable reason.
     */
    public function isSafe(string $url, ?string &$errorMessage = null): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            $errorMessage = 'Could not parse host from URL.';

            return false;
        }

        // Raw IP literal — check directly.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $errorMessage = "IP address {$host} is in a private or reserved range.";

                return false;
            }

            return true;
        }

        // Hostname — resolve and check every returned IP.
        $resolved = @gethostbynamel($host);

        if ($resolved === false || $resolved === []) {
            $errorMessage = "Hostname {$host} could not be resolved.";

            return false;
        }

        foreach ($resolved as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $errorMessage = "Hostname {$host} resolves to private or reserved IP {$ip}.";

                return false;
            }
        }

        return true;
    }
}
