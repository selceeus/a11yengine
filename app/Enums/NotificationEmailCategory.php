<?php

namespace App\Enums;

enum NotificationEmailCategory: string
{
    case Scans = 'scans';
    case ScanFailures = 'scan_failures';
    case Reports = 'reports';
    case Issues = 'issues';

    public function label(): string
    {
        return match ($this) {
            self::Scans => 'Scan Notifications',
            self::ScanFailures => 'Scan Failure Alerts',
            self::Reports => 'Reports & Digests',
            self::Issues => 'Issue Notifications',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Scans => 'Sent when a scan completes successfully.',
            self::ScanFailures => 'Sent when a scan fails to complete.',
            self::Reports => 'Weekly accessibility digest and governance reports.',
            self::Issues => 'Sent when an issue is assigned or mentioned.',
        };
    }
}
