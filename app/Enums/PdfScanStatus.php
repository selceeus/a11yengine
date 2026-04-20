<?php

namespace App\Enums;

enum PdfScanStatus: string
{
    case Pending = 'pending';
    case Scanning = 'scanning';
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
