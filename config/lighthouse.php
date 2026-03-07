<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lighthouse Binary Path
    |--------------------------------------------------------------------------
    |
    | Absolute path to the Lighthouse CLI executable. Defaults to the locally
    | installed binary in node_modules. Override via LIGHTHOUSE_BINARY in
    | your .env file (e.g. for CI environments with a global install).
    |
    */

    'binary' => env('LIGHTHOUSE_BINARY', base_path('node_modules/.bin/lighthouse')),

    /*
    |--------------------------------------------------------------------------
    | Process Timeout (seconds)
    |--------------------------------------------------------------------------
    |
    | Maximum number of seconds a single Lighthouse process is allowed to run
    | before it is forcefully terminated. Lighthouse is slower than axe — tuned
    | per-page rather than per-crawl.
    | Override via LIGHTHOUSE_TIMEOUT in your .env file.
    |
    */

    'timeout' => (int) env('LIGHTHOUSE_TIMEOUT', 120),

    /*
    |--------------------------------------------------------------------------
    | Max Pages Per Scan
    |--------------------------------------------------------------------------
    |
    | Maximum number of discovered pages that will receive a Lighthouse job.
    | Jobs are dispatched for the first N pages returned by the crawler.
    | Set to 0 to disable Lighthouse scanning entirely.
    | Override via LIGHTHOUSE_MAX_PAGES in your .env file.
    |
    */

    'max_pages' => (int) env('LIGHTHOUSE_MAX_PAGES', 10),

];
