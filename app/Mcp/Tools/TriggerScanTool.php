<?php

namespace App\Mcp\Tools;

use App\Domain\Scans\ScanConfig;
use App\Enums\ScanStatus;
use App\Jobs\RunScanJob;
use App\Models\Agency;
use App\Models\Property;
use App\Models\Scan;
use App\Models\Scopes\TenantScope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Trigger a new accessibility scan for a property. The scan will be queued and run asynchronously using the property\'s default scan configuration.')]
class TriggerScanTool extends Tool
{
    public function __construct(private readonly Agency $agency) {}

    public function handle(Request $request): Response
    {
        $request->validate([
            'property_slug' => ['required', 'string'],
        ]);

        $property = Property::withoutGlobalScope(TenantScope::class)
            ->where('agency_id', $this->agency->id)
            ->where('slug', $request->get('property_slug'))
            ->first();

        if ($property === null) {
            return Response::error('Property not found for the given slug.');
        }

        $resolvedConfig = $property->defaultScanConfig()->merge(new ScanConfig);

        $scan = Scan::create([
            'agency_id' => $this->agency->id,
            'organization_id' => $property->organization_id,
            'property_id' => $property->id,
            'status' => ScanStatus::Pending,
            'scan_config' => $resolvedConfig->toArray(),
        ]);

        RunScanJob::dispatch($scan);

        return Response::json([
            'scan_id' => $scan->id,
            'status' => $scan->status->value,
            'property' => ['id' => $property->id, 'name' => $property->name, 'slug' => $property->slug],
            'message' => 'Scan queued successfully.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'property_slug' => $schema->string()->description('The slug of the property to scan.')->required(),
        ];
    }
}
