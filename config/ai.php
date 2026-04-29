<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    | Set AI_DRIVER in your .env to switch providers without code changes.
    | Supported: "openai", "anthropic"
    */
    'default' => env('AI_DRIVER', 'openai'),

    'providers' => [
        'openai' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o'),
            'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 4096),
        ],

        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-3-7-sonnet-20250219'),
            'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 4096),
        ],
    ],

    'audit' => [
        'auto_generate_on_scan_complete' => (bool) env('AI_AUDIT_AUTO_GENERATE', false),
        'max_issues_in_prompt' => (int) env('AI_AUDIT_MAX_ISSUES', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Embeddings
    |--------------------------------------------------------------------------
    | Embeddings always use OpenAI's text-embedding-3-small model regardless
    | of the AI_DRIVER setting. Configure this separately to avoid silent
    | misconfiguration when AI_DRIVER=anthropic.
    */
    'embeddings' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('EMBEDDING_MODEL', 'text-embedding-3-small'),
        'dimensions' => (int) env('EMBEDDING_DIMENSIONS', 1536),
    ],
];
