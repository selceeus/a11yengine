<?php

namespace App\Enums;

enum AuditStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Failed => true,
            default => false,
        };
    }
}
