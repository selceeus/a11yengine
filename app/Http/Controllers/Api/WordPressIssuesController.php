<?php

namespace App\Http\Controllers\Api;

use App\Enums\IssueStatus;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\Property;
use Illuminate\Http\JsonResponse;

class WordPressIssuesController extends Controller
{
    public function __invoke(string $propertySlug): JsonResponse
    {
        $agency = app(Agency::class);

        $property = Property::withoutGlobalScopes()
            ->where('agency_id', $agency->id)
            ->where('slug', $propertySlug)
            ->firstOrFail();

        $issues = Issue::withoutGlobalScopes()
            ->where('property_id', $property->id)
            ->whereIn('status', array_map(fn (IssueStatus $s) => $s->value, IssueStatus::activeStatuses()))
            ->orderByDesc('risk_weight')
            ->limit(50)
            ->get(['id', 'rule_key', 'page_url', 'severity', 'wcag_criteria', 'description', 'status', 'first_detected_at'])
            ->map(fn (Issue $i) => [
                'id' => $i->id,
                'rule_key' => $i->rule_key,
                'page_url' => $i->page_url,
                'severity' => $i->severity->value,
                'wcag_criteria' => $i->wcag_criteria,
                'description' => $i->description,
                'status' => $i->status->value,
                'first_detected_at' => $i->first_detected_at?->toISOString(),
            ])
            ->values()
            ->all();

        return response()->json([
            'property' => [
                'id' => $property->id,
                'name' => $property->name,
                'slug' => $property->slug,
            ],
            'data' => $issues,
            'generated_at' => now()->toISOString(),
        ]);
    }
}
