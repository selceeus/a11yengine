<?php

use App\Enums\IssueStatus;
use App\Enums\PropertyIndustry;
use App\Mcp\Resources\PropertyLegalRiskResource;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\LawsuitEmbedding;
use App\Models\Organization;
use App\Models\Property;
use App\Services\EmbeddingService;
use Laravel\Mcp\Request;
use Mockery;

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    app()->instance(Agency::class, $this->agency);

    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->create([
            'slug' => 'legal-test',
            'industry' => PropertyIndustry::Retail,
        ]);

    $this->mockEmbedding = Mockery::mock(EmbeddingService::class);
    $this->mockEmbedding->allows('embed')->andReturn(testVector([1.0, 0.0]));
    app()->instance(EmbeddingService::class, $this->mockEmbedding);
});

it('returns legal risk profile with industry and baseline risk', function (): void {
    $resource = app(PropertyLegalRiskResource::class);
    $data = json_decode((string) $resource->handle(new Request(['slug' => 'legal-test']))->content(), true);

    expect($data['property']['slug'])->toBe('legal-test')
        ->and($data['property']['industry'])->toBe('retail')
        ->and($data['baseline_risk'])->toBe('high')
        ->and($data['open_issue_count'])->toBe(0)
        ->and($data['top_lawsuits'])->toBe([]);
});

it('returns top matching lawsuits when open issues exist', function (): void {
    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'rule_key' => 'image-alt',
        'wcag_criteria' => '1.1.1',
        'risk_weight' => 10.0,
    ]);

    LawsuitEmbedding::create([
        'case_name' => 'Robles v. Dominos',
        'filed_year' => 2019,
        'industry' => 'retail',
        'violation_type' => 'screen reader incompatibility',
        'outcome' => 'plaintiff_won',
        'settlement_amount' => null,
        'summary' => 'Blind plaintiff unable to order pizza.',
        'embedding' => testVector([1.0, 0.0]),
    ]);

    $resource = app(PropertyLegalRiskResource::class);
    $data = json_decode((string) $resource->handle(new Request(['slug' => 'legal-test']))->content(), true);

    expect($data['open_issue_count'])->toBe(1)
        ->and($data['top_lawsuits'])->toHaveCount(1)
        ->and($data['top_lawsuits'][0]['case_name'])->toBe('Robles v. Dominos');
});

it('elevates risk level based on plaintiff wins', function (): void {
    $property = Property::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->create([
            'slug' => 'low-risk-prop',
            'industry' => PropertyIndustry::Technology,
        ]);

    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $property->id,
        'status' => IssueStatus::Open,
        'rule_key' => 'image-alt',
        'wcag_criteria' => '1.1.1',
        'risk_weight' => 10.0,
    ]);

    LawsuitEmbedding::create([
        'case_name' => 'Case A',
        'filed_year' => 2020,
        'industry' => 'technology',
        'violation_type' => 'images',
        'outcome' => 'plaintiff_won',
        'settlement_amount' => null,
        'summary' => 'Tech company sued for inaccessible site.',
        'embedding' => testVector([1.0, 0.0]),
    ]);

    LawsuitEmbedding::create([
        'case_name' => 'Case B',
        'filed_year' => 2021,
        'industry' => 'technology',
        'violation_type' => 'keyboard navigation',
        'outcome' => 'plaintiff_won',
        'settlement_amount' => null,
        'summary' => 'Another tech lawsuit.',
        'embedding' => testVector([0.9, 0.1]),
    ]);

    $resource = app(PropertyLegalRiskResource::class);
    $data = json_decode((string) $resource->handle(new Request(['slug' => 'low-risk-prop']))->content(), true);

    // Technology baseline is 'low', but 2 plaintiff wins elevate to 'high'
    expect($data['baseline_risk'])->toBe('low')
        ->and($data['risk_level'])->toBe('high');
});

it('returns an error for an unknown slug', function (): void {
    $resource = app(PropertyLegalRiskResource::class);
    $response = $resource->handle(new Request(['slug' => 'nonexistent']));

    expect((string) $response->content())->toContain('Property not found');
});

it('does not expose properties from another agency', function (): void {
    $otherAgency = Agency::factory()->create();
    $otherOrg = Organization::factory()->create(['agency_id' => $otherAgency->id]);
    Property::factory()->for($otherAgency)->for($otherOrg)->create(['slug' => 'other-prop']);

    $resource = app(PropertyLegalRiskResource::class);
    $response = $resource->handle(new Request(['slug' => 'other-prop']));

    expect((string) $response->content())->toContain('Property not found');
});
