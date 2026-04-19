<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Property;
use Illuminate\Http\JsonResponse;

class WordPressPropertiesController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $agency = app(Agency::class);

        $properties = Property::withoutGlobalScopes()
            ->where('agency_id', $agency->id)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'base_url', 'industry', 'status'])
            ->map(fn (Property $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'base_url' => $p->base_url,
                'industry' => $p->industry?->value,
                'status' => $p->status,
            ])
            ->values()
            ->all();

        return response()->json([
            'data' => $properties,
            'generated_at' => now()->toISOString(),
        ]);
    }
}
