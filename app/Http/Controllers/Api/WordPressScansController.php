<?php

namespace App\Http\Controllers\Api;

use App\Domain\Scans\ScanConfig;
use App\Enums\ActivityLogEvent;
use App\Enums\ScanStatus;
use App\Http\Controllers\Controller;
use App\Jobs\RunScanJob;
use App\Models\Agency;
use App\Models\ApiKey;
use App\Models\Property;
use App\Models\Scan;
use App\Models\Scopes\TenantScope;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WordPressScansController extends Controller
{
    public function __invoke(Request $request, string $propertySlug): JsonResponse
    {
        $agency = app(Agency::class);
        $apiKey = app(ApiKey::class);

        $property = Property::withoutGlobalScope(TenantScope::class)
            ->where('agency_id', $agency->id)
            ->where('slug', $propertySlug)
            ->firstOrFail();

        $resolvedConfig = $property->defaultScanConfig()->merge(new ScanConfig);

        $scan = Scan::create([
            'agency_id' => $agency->id,
            'organization_id' => $property->organization_id,
            'property_id' => $property->id,
            'status' => ScanStatus::Pending,
            'scan_config' => $resolvedConfig->toArray(),
        ]);

        RunScanJob::dispatch($scan);

        ActivityLogger::logForApiKey(
            $apiKey,
            ActivityLogEvent::ScanStarted,
            $scan,
            $property->name,
        );

        return response()->json([
            'scan_id' => $scan->id,
            'property' => [
                'id' => $property->id,
                'name' => $property->name,
                'slug' => $property->slug,
            ],
            'status' => $scan->status->value,
            'message' => 'Scan queued successfully.',
            'created_at' => $scan->created_at->toISOString(),
        ], 201);
    }
}
