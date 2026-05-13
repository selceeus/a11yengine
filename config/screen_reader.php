<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Screen Reader Scanning
    |--------------------------------------------------------------------------
    |
    | Controls whether the virtual screen reader check suite runs as part of
    | each page scan. When disabled, no RunScreenReaderAuditJob jobs are
    | dispatched and screen_reader_completed is stored as null on ScanPage.
    |
    */
    'enabled' => env('SCREEN_READER_ENABLED', true),
];
