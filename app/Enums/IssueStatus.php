<?php

namespace App\Enums;

enum IssueStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Ignored = 'ignored';
    case FalsePositive = 'false_positive';

    /** @return list<self> */
    public static function terminalStatuses(): array
    {
        return [self::Resolved, self::Ignored, self::FalsePositive];
    }

    /** @return list<self> */
    public static function activeStatuses(): array
    {
        return [self::Open, self::InProgress];
    }

    public function isTerminal(): bool
    {
        return in_array($this, self::terminalStatuses(), strict: true);
    }
}
