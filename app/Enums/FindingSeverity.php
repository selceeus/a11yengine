<?php

namespace App\Enums;

enum FindingSeverity: string
{
    case CRITICAL = 'critical';
    case SERIOUS = 'serious';
    case MODERATE = 'moderate';
    case MINOR = 'minor';
    case INFO = 'info';

    public function toPriority(): int
    {
        return match ($this) {
            self::CRITICAL => 1,
            self::SERIOUS => 2,
            self::MODERATE => 3,
            self::MINOR, self::INFO => 4,
        };
    }
}
