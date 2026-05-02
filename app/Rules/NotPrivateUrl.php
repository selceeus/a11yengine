<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotPrivateUrl implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            $fail('The :attribute must be a valid URL.');

            return;
        }

        // Reject raw IP literals that are private/loopback/link-local/multicast.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $fail('The :attribute must not point to a private or reserved IP address.');

                return;
            }
        }

        // Resolve hostname and check every returned IP.
        $resolved = @gethostbynamel($host);

        if ($resolved === false || $resolved === []) {
            $fail('The :attribute hostname could not be resolved.');

            return;
        }

        foreach ($resolved as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $fail('The :attribute must not resolve to a private or reserved IP address.');

                return;
            }
        }
    }
}
