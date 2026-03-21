<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\RemediateViolationPrompt;
use App\Mcp\Resources\PropertyIssuesResource;
use App\Mcp\Resources\PropertyRiskSummaryResource;
use App\Mcp\Tools\GetIssueRemediationTool;
use App\Mcp\Tools\GetPropertyIssuesTool;
use App\Mcp\Tools\GetScanFindingsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('property-accessibility')]
#[Version('1.0.0')]
#[Instructions('Provides accessibility audit data for web properties. Use tools to query open issues, AI-generated remediations, and raw scan findings. Use resources to read structured issue lists and risk summaries by property slug.')]
class PropertyAccessibilityServer extends Server
{
    protected array $tools = [
        GetPropertyIssuesTool::class,
        GetIssueRemediationTool::class,
        GetScanFindingsTool::class,
    ];

    protected array $resources = [
        PropertyIssuesResource::class,
        PropertyRiskSummaryResource::class,
    ];

    protected array $prompts = [
        RemediateViolationPrompt::class,
    ];
}
