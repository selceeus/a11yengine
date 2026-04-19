<?php

namespace App\Enums;

enum IntegrationProvider: string
{
    case Jira = 'jira';
    case Wrike = 'wrike';
    case Asana = 'asana';
    case Monday = 'monday';
    case Linear = 'linear';
    case GitHub = 'github';
    case ClickUp = 'clickup';
    case AzureDevOps = 'azure_devops';
    case Trello = 'trello';
    case Notion = 'notion';
    case Basecamp = 'basecamp';

    public function label(): string
    {
        return match ($this) {
            self::Jira => 'Jira',
            self::Wrike => 'Wrike',
            self::Asana => 'Asana',
            self::Monday => 'Monday.com',
            self::Linear => 'Linear',
            self::GitHub => 'GitHub Issues',
            self::ClickUp => 'ClickUp',
            self::AzureDevOps => 'Azure DevOps',
            self::Trello => 'Trello',
            self::Notion => 'Notion',
            self::Basecamp => 'Basecamp',
        };
    }

    public function authType(): string
    {
        return match ($this) {
            self::Jira => 'basic',
            self::Wrike, self::Asana, self::Linear, self::GitHub,
            self::ClickUp, self::Monday => 'token',
            self::AzureDevOps => 'pat',
            self::Trello => 'apikey',
            self::Notion => 'token',
            self::Basecamp => 'token',
        };
    }

    public function supportsWebhooks(): bool
    {
        return match ($this) {
            self::Jira, self::GitHub, self::Linear, self::Asana, self::Wrike => true,
            default => false,
        };
    }

    public function isImplemented(): bool
    {
        return match ($this) {
            self::Jira, self::Wrike, self::Asana, self::Linear, self::GitHub,
            self::Monday, self::ClickUp, self::AzureDevOps, self::Trello, self::Notion, self::Basecamp => true,
            default => false,
        };
    }

    /**
     * @return list<array{key: string, label: string, type: string, required: bool}>
     */
    public function credentialFields(): array
    {
        return match ($this) {
            self::Jira => [
                ['key' => 'base_url', 'label' => 'Jira Base URL', 'type' => 'url', 'required' => true],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                ['key' => 'api_token', 'label' => 'API Token', 'type' => 'password', 'required' => true],
                ['key' => 'project_key', 'label' => 'Project Key', 'type' => 'text', 'required' => true],
            ],
            self::Wrike => [
                ['key' => 'access_token', 'label' => 'Access Token', 'type' => 'password', 'required' => true],
                ['key' => 'folder_id', 'label' => 'Folder ID', 'type' => 'text', 'required' => true],
            ],
            self::Asana => [
                ['key' => 'access_token', 'label' => 'Personal Access Token', 'type' => 'password', 'required' => true],
                ['key' => 'project_gid', 'label' => 'Project GID', 'type' => 'text', 'required' => true],
                ['key' => 'webhook_secret', 'label' => 'Webhook Secret', 'type' => 'password', 'required' => false],
            ],
            self::Linear => [
                ['key' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true],
                ['key' => 'team_id', 'label' => 'Team ID', 'type' => 'text', 'required' => true],
            ],
            self::GitHub => [
                ['key' => 'token', 'label' => 'Personal Access Token', 'type' => 'password', 'required' => true],
                ['key' => 'owner', 'label' => 'Owner (user or org)', 'type' => 'text', 'required' => true],
                ['key' => 'repo', 'label' => 'Repository Name', 'type' => 'text', 'required' => true],
                ['key' => 'webhook_secret', 'label' => 'Webhook Secret', 'type' => 'password', 'required' => false],
            ],
            self::Monday => [
                ['key' => 'api_token', 'label' => 'API Token', 'type' => 'password', 'required' => true],
                ['key' => 'board_id', 'label' => 'Board ID', 'type' => 'text', 'required' => true],
            ],
            self::ClickUp => [
                ['key' => 'api_token', 'label' => 'API Token', 'type' => 'password', 'required' => true],
                ['key' => 'list_id', 'label' => 'List ID', 'type' => 'text', 'required' => true],
            ],
            self::AzureDevOps => [
                ['key' => 'organization', 'label' => 'Organization', 'type' => 'text', 'required' => true],
                ['key' => 'project', 'label' => 'Project', 'type' => 'text', 'required' => true],
                ['key' => 'pat', 'label' => 'Personal Access Token', 'type' => 'password', 'required' => true],
            ],
            self::Trello => [
                ['key' => 'api_key', 'label' => 'API Key', 'type' => 'text', 'required' => true],
                ['key' => 'token', 'label' => 'Token', 'type' => 'password', 'required' => true],
                ['key' => 'list_id', 'label' => 'List ID', 'type' => 'text', 'required' => true],
            ],
            self::Notion => [
                ['key' => 'integration_token', 'label' => 'Integration Token', 'type' => 'password', 'required' => true],
                ['key' => 'database_id', 'label' => 'Database ID', 'type' => 'text', 'required' => true],
            ],
            self::Basecamp => [
                ['key' => 'access_token', 'label' => 'Access Token', 'type' => 'password', 'required' => true],
                ['key' => 'account_id', 'label' => 'Account ID', 'type' => 'text', 'required' => true],
                ['key' => 'project_id', 'label' => 'Project ID', 'type' => 'text', 'required' => true],
            ],
        };
    }
}
