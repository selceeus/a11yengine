<?php

namespace App\Enums;

enum FindingSeverity: string
{
    case CRITICAL = 'critical';
    case SERIOUS = 'serious';
    case MODERATE = 'moderate';
    case MINOR = 'minor';
    case INFO = 'info';
}
