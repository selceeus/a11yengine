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
    | Enable Lighthouse Scanning
    |--------------------------------------------------------------------------
    |
    | When enabled, a RunLighthouseScanJob is dispatched for every page
    | discovered during a scan. Set to false (or LIGHTHOUSE_ENABLED=false)
    | to disable Lighthouse scanning entirely, e.g. in CI environments
    | without Chromium.
    |
    */

    'enabled' => (bool) env('LIGHTHOUSE_ENABLED', true),
    'chrome_path' => env('LIGHTHOUSE_CHROME_PATH'),
];
