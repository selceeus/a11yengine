<?php

namespace App\Enums;

enum MessagingPlatform: string
{
    case Slack = 'slack';
    case Teams = 'teams';
    case Discord = 'discord';

    public function label(): string
    {
        return match ($this) {
            self::Slack => 'Slack',
            self::Teams => 'Microsoft Teams',
            self::Discord => 'Discord',
        };
    }
}
