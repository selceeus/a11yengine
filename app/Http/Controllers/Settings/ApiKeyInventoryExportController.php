<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApiKeyInventoryExportController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        $filename = 'api-keys-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'name',
                'key_prefix',
                'scopes',
                'created_by',
                'created_at',
                'last_used_at',
                'expires_at',
                'revoked_at',
                'status',
            ]);

            ApiKey::query()
                ->with('createdBy:id,name')
                ->orderByDesc('created_at')
                ->chunk(200, function ($keys) use ($handle): void {
                    foreach ($keys as $key) {
                        fputcsv($handle, [
                            $key->name,
                            $key->key_prefix,
                            implode(', ', $key->scopes ?? []),
                            $key->createdBy?->name,
                            $key->created_at?->toIso8601String(),
                            $key->last_used_at?->toIso8601String(),
                            $key->expires_at?->toIso8601String(),
                            $key->revoked_at?->toIso8601String(),
                            $key->isActive() ? 'active' : 'inactive',
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
