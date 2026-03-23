<?php

namespace App\Enums;

enum IntegrationStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Error => 'Error',
        };
    }
}
