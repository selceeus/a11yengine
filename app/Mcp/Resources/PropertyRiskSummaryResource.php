<?php

namespace App\Mcp\Resources;

use App\Models\Agency;
use App\Models\Property;
use App\Models\Scopes\TenantScope;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Description('Risk score and severity breakdown for a property, including open issue counts grouped by severity level.')]
#[MimeType('application/json')]
class PropertyRiskSummaryResource extends Resource implements HasUriTemplate
{
    public function __construct(private readonly Agency $agency) {}

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('property://{slug}/risk-summary');
    }

    public function handle(Request $request): Response
    {
        $slug = (string) $request->get('slug', '');

        $property = Property::withoutGlobalScope(TenantScope::class)
            ->where('agency_id', $this->agency->id)
            ->where('slug', $slug)
            ->first();

        if ($property === null) {
            return Response::error('Property not found for slug: '.$slug);
        }

        $counts = DB::table('issues')
            ->where('property_id', $property->id)
            ->whereIn('status', ['open', 'in_progress'])
            ->select('severity', DB::raw('COUNT(*) as count'), DB::raw('SUM(risk_weight) as total_risk'))
            ->groupBy('severity')
            ->get()
            ->keyBy('severity');

        $summary = [
            'property' => ['id' => $property->id, 'name' => $property->name, 'slug' => $property->slug],
            'open_issue_counts' => [
                'critical' => (int) ($counts['critical']->count ?? 0),
                'high' => (int) ($counts['high']->count ?? 0),
                'medium' => (int) ($counts['medium']->count ?? 0),
                'low' => (int) ($counts['low']->count ?? 0),
            ],
            'total_open_issues' => $counts->sum('count'),
            'total_risk_weight' => round((float) $counts->sum('total_risk'), 4),
        ];

        return Response::json($summary);
    }
}
