<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\RemediateViolationPrompt;
use App\Mcp\Resources\PendingAlertsResource;
use App\Mcp\Resources\PropertyComplianceResource;
use App\Mcp\Resources\PropertyIssuesResource;
use App\Mcp\Resources\PropertyLegalRiskResource;
use App\Mcp\Resources\PropertyRiskSummaryResource;
use App\Mcp\Tools\GetIssueRemediationTool;
use App\Mcp\Tools\GetPropertyIssuesTool;
use App\Mcp\Tools\GetRelatedLawsuitsTool;
use App\Mcp\Tools\GetScanFindingsTool;
use App\Mcp\Tools\GetSimilarRemediationsTool;
use App\Mcp\Tools\TriggerScanTool;
use App\Mcp\Tools\UpdateIssueStatusTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('property-accessibility')]
#[Version('1.0.0')]
#[Instructions('Provides accessibility audit data for web properties. Use tools to query open issues, AI-generated remediations, raw scan findings, related ADA lawsuit precedents, and similar remediation patterns. Use resources to read structured issue lists, risk summaries, and legal risk profiles by property slug.')]
class PropertyAccessibilityServer extends Server
{
    protected array $tools = [
        GetPropertyIssuesTool::class,
        GetIssueRemediationTool::class,
        GetScanFindingsTool::class,
        GetRelatedLawsuitsTool::class,
        GetSimilarRemediationsTool::class,
        UpdateIssueStatusTool::class,
        TriggerScanTool::class,
    ];

    protected array $resources = [
        PropertyIssuesResource::class,
        PropertyRiskSummaryResource::class,
        PropertyLegalRiskResource::class,
        PropertyComplianceResource::class,
        PendingAlertsResource::class,
    ];

    protected array $prompts = [
        RemediateViolationPrompt::class,
    ];
}
