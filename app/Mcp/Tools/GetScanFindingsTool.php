<?php

namespace App\Mcp\Tools;

use App\Enums\ScanStatus;
use App\Models\Agency;
use App\Models\Property;
use App\Models\Scopes\TenantScope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Return raw accessibility findings from a property scan. Defaults to the most recently completed scan. Pass scan_id to target a specific scan.')]
class GetScanFindingsTool extends Tool
{
    public function __construct(private readonly Agency $agency) {}

    public function handle(Request $request): Response
    {
        $request->validate([
            'property_slug' => ['required', 'string'],
            'scan_id' => ['nullable', 'integer'],
        ]);

        $property = Property::withoutGlobalScope(TenantScope::class)
            ->where('agency_id', $this->agency->id)
            ->where('slug', $request->get('property_slug'))
            ->first();

        if ($property === null) {
            return Response::error('Property not found for the given slug.');
        }

        $scanQuery = $property->scans()
            ->withoutGlobalScope(TenantScope::class)
            ->where('status', ScanStatus::Completed);

        if ($request->get('scan_id') !== null) {
            $scan = $scanQuery->find($request->get('scan_id'));
        } else {
            $scan = $scanQuery->latest('completed_at')->first();
        }

        if ($scan === null) {
            return Response::error('No completed scan found for this property.');
        }

        $findings = $scan->findings()
            ->withoutGlobalScope(TenantScope::class)
            ->get([
                'id', 'rule_key', 'severity', 'wcag_category', 'wcag_criteria',
                'description', 'element_identifier', 'element_html', 'page_url',
                'message', 'detected_at',
            ]);

        return Response::json([
            'property' => ['id' => $property->id, 'name' => $property->name, 'slug' => $property->slug],
            'scan' => ['id' => $scan->id, 'completed_at' => $scan->completed_at],
            'total' => $findings->count(),
            'findings' => $findings->toArray(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'property_slug' => $schema->string()->description('The slug of the property to query.')->required(),
            'scan_id' => $schema->integer()->description('ID of a specific scan. Omit to use the latest completed scan.'),
        ];
    }
}
