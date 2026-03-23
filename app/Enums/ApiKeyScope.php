<?php

namespace App\Enums;

enum ApiKeyScope: string
{
    case ScansRead = 'scans:read';
    case ScansTrigger = 'scans:trigger';
    case IssuesRead = 'issues:read';
    case ReportsRead = 'reports:read';
    case Mcp = 'mcp';
    case WordPress = 'wordpress';

    public function label(): string
    {
        return match ($this) {
            self::ScansRead => 'Read Scans',
            self::ScansTrigger => 'Trigger Scans',
            self::IssuesRead => 'Read Issues',
            self::ReportsRead => 'Read Reports',
            self::Mcp => 'MCP Access',
            self::WordPress => 'WordPress Plugin',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ScansRead => 'View scan results and history',
            self::ScansTrigger => 'Initiate new scans via API',
            self::IssuesRead => 'Read accessibility issues',
            self::ReportsRead => 'Access governance reports',
            self::Mcp => 'Connect AI tools via MCP protocol',
            self::WordPress => 'Authenticate the WordPress plugin',
        };
    }
}
