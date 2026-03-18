<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Active AI Driver
    |--------------------------------------------------------------------------
    | Set AI_DRIVER in your .env to switch providers without code changes.
    | Supported: "openai", "anthropic"
    */
    'driver' => env('AI_DRIVER', 'openai'),

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o'),
            'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 4096),
            'temperature' => 0.2,
            'timeout' => 120,
            'base_url' => 'https://api.openai.com/v1',
        ],

        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-3-7-sonnet-20250219'),
            'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 4096),
            'temperature' => 0.2,
            'timeout' => 120,
            'base_url' => 'https://api.anthropic.com/v1',
            'version' => '2023-06-01',
        ],
    ],

    'audit' => [
        'auto_generate_on_scan_complete' => (bool) env('AI_AUDIT_AUTO_GENERATE', false),
        'max_issues_in_prompt' => (int) env('AI_AUDIT_MAX_ISSUES', 30),
    ],
];
