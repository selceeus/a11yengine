<?php

use App\Mcp\Servers\PropertyAccessibilityServer;
use App\Mcp\Tools\GetRelatedLawsuitsTool;
use App\Models\Agency;
use App\Models\LawsuitEmbedding;
use App\Services\EmbeddingService;

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    app()->instance(Agency::class, $this->agency);

    $this->mockEmbedding = Mockery::mock(EmbeddingService::class);
    $this->mockEmbedding->allows('embed')->andReturn([1.0, 0.0]);
    $this->mockEmbedding->allows('cosineSimilarity')->andReturnUsing(
        function (array $a, array $b): float {
            return array_sum(array_map(fn ($x, $y) => $x * $y, $a, $b));
        }
    );
    app()->instance(EmbeddingService::class, $this->mockEmbedding);
});

it('returns matched lawsuits for a rule and criterion', function (): void {
    LawsuitEmbedding::create([
        'case_name' => 'Robles v. Dominos',
        'filed_year' => 2019,
        'industry' => 'retail',
        'violation_type' => 'screen reader incompatibility',
        'outcome' => 'plaintiff_won',
        'settlement_amount' => null,
        'summary' => 'Blind plaintiff unable to order pizza online.',
        'embedding' => [1.0, 0.0],
    ]);

    PropertyAccessibilityServer::tool(GetRelatedLawsuitsTool::class, [
        'rule_key' => 'image-alt',
        'wcag_criteria' => '1.1.1',
    ])->assertOk()
        ->assertSee('Robles v. Dominos')
        ->assertSee('plaintiff_won');
});

it('filters lawsuits by industry', function (): void {
    LawsuitEmbedding::create([
        'case_name' => 'Healthcare Lawsuit',
        'filed_year' => 2021,
        'industry' => 'healthcare',
        'violation_type' => 'forms',
        'outcome' => 'settled',
        'settlement_amount' => 50000,
        'summary' => 'Hospital website inaccessible.',
        'embedding' => [1.0, 0.0],
    ]);

    LawsuitEmbedding::create([
        'case_name' => 'Retail Lawsuit',
        'filed_year' => 2020,
        'industry' => 'retail',
        'violation_type' => 'images',
        'outcome' => 'plaintiff_won',
        'settlement_amount' => null,
        'summary' => 'Ecommerce site inaccessible.',
        'embedding' => [1.0, 0.0],
    ]);

    PropertyAccessibilityServer::tool(GetRelatedLawsuitsTool::class, [
        'rule_key' => 'image-alt',
        'wcag_criteria' => '1.1.1',
        'industry' => 'healthcare',
    ])->assertOk()
        ->assertSee('Healthcare Lawsuit')
        ->assertSee('healthcare')
        ->assertDontSee('Retail Lawsuit');
});

it('returns empty results when no lawsuits exist', function (): void {
    PropertyAccessibilityServer::tool(GetRelatedLawsuitsTool::class, [
        'rule_key' => 'color-contrast',
        'wcag_criteria' => '1.4.3',
    ])->assertOk()
        ->assertSee('"total":0');
});

it('respects the limit parameter', function (): void {
    for ($i = 1; $i <= 5; $i++) {
        LawsuitEmbedding::create([
            'case_name' => "Case {$i}",
            'filed_year' => 2020 + $i,
            'industry' => 'retail',
            'violation_type' => 'images',
            'outcome' => 'settled',
            'settlement_amount' => null,
            'summary' => "Summary {$i}",
            'embedding' => [1.0, 0.0],
        ]);
    }

    PropertyAccessibilityServer::tool(GetRelatedLawsuitsTool::class, [
        'rule_key' => 'image-alt',
        'wcag_criteria' => '1.1.1',
        'limit' => 2,
    ])->assertOk()
        ->assertSee('"total":2');
});
