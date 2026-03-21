<?php

namespace App\Mcp\Tools;

use App\Enums\IssueStatus;
use App\Models\Agency;
use App\Models\Property;
use App\Models\Scopes\TenantScope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Return open accessibility issues for a property, optionally filtered by severity (critical, high, medium, low) and/or status.')]
class GetPropertyIssuesTool extends Tool
{
    public function __construct(private readonly Agency $agency) {}

    public function handle(Request $request): Response
    {
        $request->validate([
            'property_slug' => ['required', 'string'],
            'severity' => ['nullable', 'string', 'in:critical,high,medium,low'],
            'status' => ['nullable', 'string', 'in:open,in_progress,resolved,ignored,false_positive'],
        ]);

        $property = Property::withoutGlobalScope(TenantScope::class)
            ->where('agency_id', $this->agency->id)
            ->where('slug', $request->get('property_slug'))
            ->first();

        if ($property === null) {
            return Response::error('Property not found for the given slug.');
        }

        $issues = $property->issues()
            ->withoutGlobalScope(TenantScope::class)
            ->when($request->get('severity'), fn ($q, $s) => $q->where('severity', $s))
            ->when(
                $request->get('status'),
                fn ($q, $s) => $q->where('status', $s),
                fn ($q) => $q->whereIn('status', [IssueStatus::Open->value, IssueStatus::InProgress->value])
            )
            ->orderByDesc('risk_weight')
            ->get([
                'id', 'rule_key', 'page_url', 'severity', 'status',
                'wcag_category', 'wcag_criteria', 'description',
                'occurrence_count', 'risk_weight', 'first_detected_at', 'last_detected_at',
            ]);

        return Response::json([
            'property' => ['id' => $property->id, 'name' => $property->name, 'slug' => $property->slug],
            'total' => $issues->count(),
            'issues' => $issues->toArray(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'property_slug' => $schema->string()->description('The slug of the property to query.')->required(),
            'severity' => $schema->string()->enum(['critical', 'high', 'medium', 'low'])->description('Filter by issue severity. Omit to return all active issues.'),
            'status' => $schema->string()->enum(['open', 'in_progress', 'resolved', 'ignored', 'false_positive'])->description('Filter by status. Defaults to open + in_progress.'),
        ];
    }
}
