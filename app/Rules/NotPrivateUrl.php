<?php

namespace App\Rules;

use App\Services\IpSafetyChecker;
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

        $checker = app(IpSafetyChecker::class);

        if (! $checker->isSafe($value, $reason)) {
            $fail('The :attribute must not point to a private, reserved, or unresolvable address.');
        }
    }
}
