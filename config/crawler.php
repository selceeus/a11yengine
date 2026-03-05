<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Crawler Script Path
    |--------------------------------------------------------------------------
    |
    | Absolute path to the Node.js axe-core crawler entry-point script.
    | Override via CRAWLER_SCRIPT_PATH in your .env file.
    |
    */

    'script_path' => env('CRAWLER_SCRIPT_PATH', base_path('crawler/index.js')),

    /*
    |--------------------------------------------------------------------------
    | Process Timeout (seconds)
    |--------------------------------------------------------------------------
    |
    | Maximum number of seconds the crawler process is allowed to run before
    | it is forcefully terminated and the scan is marked as failed.
    | Override via CRAWLER_TIMEOUT in your .env file.
    |
    */

    'timeout' => (int) env('CRAWLER_TIMEOUT', 300),

];
