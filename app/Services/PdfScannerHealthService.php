<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PdfScannerHealthService
{
    public function isAvailable(): bool
    {
        if (! config('services.pdf_scanner.enabled')) {
            return false;
        }

        return Cache::remember('pdf_scanner_health', 60, function (): bool {
            try {
                $response = Http::timeout(3)->get(config('services.pdf_scanner.url').'/api/info');

                return $response->successful();
            } catch (\Throwable) {
                return false;
            }
        });
    }
}
