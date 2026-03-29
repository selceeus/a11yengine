<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->get('q', ''));

        if (strlen($term) < 2) {
            return response()->json([
                'properties' => [],
                'organizations' => [],
                'issues' => [],
            ]);
        }

        $like = '%'.$term.'%';

        $properties = Property::query()
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('base_url', 'like', $like))
            ->select(['id', 'name', 'base_url'])
            ->limit(5)
            ->get();

        $organizations = Organization::query()
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('domain', 'like', $like))
            ->select(['id', 'name', 'domain'])
            ->limit(5)
            ->get();

        $issues = Issue::query()
            ->where(fn ($q) => $q
                ->where('description', 'like', $like)
                ->orWhere('rule_key', 'like', $like)
                ->orWhere('wcag_criteria', 'like', $like)
                ->orWhere('page_url', 'like', $like)
            )
            ->with('property:id,name')
            ->select(['id', 'rule_key', 'description', 'severity', 'status', 'property_id'])
            ->limit(5)
            ->get();

        return response()->json([
            'properties' => $properties,
            'organizations' => $organizations,
            'issues' => $issues,
        ]);
    }
}
