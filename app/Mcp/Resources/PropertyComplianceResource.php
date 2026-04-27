<?php

namespace App\Mcp\Resources;

use App\Enums\IssueStatus;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\Property;
use App\Models\Scopes\TenantScope;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Description('WCAG 2.1 compliance status for a property, showing pass/fail counts per conformance level (A, AA, AAA) based on active issues.')]
#[MimeType('application/json')]
class PropertyComplianceResource extends Resource implements HasUriTemplate
{
    public function __construct(private readonly Agency $agency) {}

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('property://{slug}/compliance');
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

        $activeStatuses = IssueStatus::activeStatusValues();

        $failingCriteria = Issue::withoutGlobalScope(TenantScope::class)
            ->where('property_id', $property->id)
            ->whereNotNull('wcag_criteria')
            ->whereIn('status', $activeStatuses)
            ->distinct()
            ->pluck('wcag_criteria')
            ->all();

        $failMap = ['wcag_a' => 0, 'wcag_aa' => 0, 'wcag_aaa' => 0];

        foreach ($failingCriteria as $criteria) {
            $level = $this->parseLevelFromCriteria((string) $criteria);

            if ($level === null) {
                continue;
            }

            $failMap[$level]++;
        }

        // WCAG 2.1 totals: A=30, AA=20, AAA=28
        $wcagA = ['pass' => max(0, 30 - $failMap['wcag_a']), 'fail' => $failMap['wcag_a'], 'total' => 30];
        $wcagAa = ['pass' => max(0, 20 - $failMap['wcag_aa']), 'fail' => $failMap['wcag_aa'], 'total' => 20];
        $wcagAaa = ['pass' => max(0, 28 - $failMap['wcag_aaa']), 'fail' => $failMap['wcag_aaa'], 'total' => 28];

        $totalCriteria = 78;
        $totalPassing = $wcagA['pass'] + $wcagAa['pass'] + $wcagAaa['pass'];
        $overallPassRate = round(($totalPassing / $totalCriteria) * 100, 1);

        return Response::json([
            'property' => ['id' => $property->id, 'name' => $property->name, 'slug' => $property->slug],
            'wcag_a' => $wcagA,
            'wcag_aa' => $wcagAa,
            'wcag_aaa' => $wcagAaa,
            'overall_pass_rate' => $overallPassRate,
            'failing_criteria' => $failingCriteria,
        ]);
    }

    private function parseLevelFromCriteria(string $criteria): ?string
    {
        if (str_ends_with($criteria, ' AAA')) {
            return 'wcag_aaa';
        }

        if (str_ends_with($criteria, ' AA')) {
            return 'wcag_aa';
        }

        if (str_ends_with($criteria, ' A')) {
            return 'wcag_a';
        }

        return null;
    }
}
