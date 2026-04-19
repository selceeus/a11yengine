<?php

namespace App\Domain\Integrations;

use App\Domain\Integrations\Contracts\ProjectManagementProvider;
use App\Domain\Integrations\Providers\AsanaProvider;
use App\Domain\Integrations\Providers\AzureDevOpsProvider;
use App\Domain\Integrations\Providers\BasecampProvider;
use App\Domain\Integrations\Providers\ClickUpProvider;
use App\Domain\Integrations\Providers\GitHubProvider;
use App\Domain\Integrations\Providers\JiraProvider;
use App\Domain\Integrations\Providers\LinearProvider;
use App\Domain\Integrations\Providers\MondayProvider;
use App\Domain\Integrations\Providers\NotionProvider;
use App\Domain\Integrations\Providers\TrelloProvider;
use App\Domain\Integrations\Providers\WrikeProvider;
use App\Enums\IntegrationProvider;
use InvalidArgumentException;

class IntegrationProviderRegistry
{
    /** @var array<string, class-string<ProjectManagementProvider>> */
    private static array $providers = [
        IntegrationProvider::Jira->value => JiraProvider::class,
        IntegrationProvider::Wrike->value => WrikeProvider::class,
        IntegrationProvider::Asana->value => AsanaProvider::class,
        IntegrationProvider::Linear->value => LinearProvider::class,
        IntegrationProvider::GitHub->value => GitHubProvider::class,
        IntegrationProvider::Monday->value => MondayProvider::class,
        IntegrationProvider::ClickUp->value => ClickUpProvider::class,
        IntegrationProvider::AzureDevOps->value => AzureDevOpsProvider::class,
        IntegrationProvider::Trello->value => TrelloProvider::class,
        IntegrationProvider::Notion->value => NotionProvider::class,
        IntegrationProvider::Basecamp->value => BasecampProvider::class,
    ];

    public static function make(IntegrationProvider $provider): ProjectManagementProvider
    {
        $class = self::$providers[$provider->value] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("No provider implementation for [{$provider->value}].");
        }

        return new $class;
    }

    public static function implemented(): array
    {
        return array_keys(self::$providers);
    }
}
