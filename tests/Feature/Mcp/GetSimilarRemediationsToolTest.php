<?php

use App\Mcp\Servers\PropertyAccessibilityServer;
use App\Mcp\Tools\GetSimilarRemediationsTool;
use App\Models\Agency;
use App\Models\RemediationEmbedding;
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

it('returns similar remediations for a rule and criterion', function (): void {
    RemediationEmbedding::create([
        'rule_key' => 'image-alt',
        'wcag_criteria' => '1.1.1',
        'description' => 'Image missing alt attribute',
        'resolution' => 'Added descriptive alt text to the img element.',
        'outcome' => 'resolved',
        'embedding' => [1.0, 0.0],
    ]);

    PropertyAccessibilityServer::tool(GetSimilarRemediationsTool::class, [
        'rule_key' => 'image-alt',
        'wcag_criteria' => '1.1.1',
    ])->assertOk()
        ->assertSee('image-alt')
        ->assertSee('Added descriptive alt text');
});

it('includes resolved_count in results', function (): void {
    for ($i = 0; $i < 3; $i++) {
        RemediationEmbedding::create([
            'rule_key' => 'image-alt',
            'wcag_criteria' => '1.1.1',
            'description' => "Image alt issue {$i}",
            'resolution' => 'Added alt text.',
            'outcome' => 'resolved',
            'embedding' => [1.0, 0.0],
        ]);
    }

    PropertyAccessibilityServer::tool(GetSimilarRemediationsTool::class, [
        'rule_key' => 'image-alt',
        'wcag_criteria' => '1.1.1',
    ])->assertOk()
        ->assertSee('"resolved_count":3');
});

it('returns empty results when no remediations exist', function (): void {
    PropertyAccessibilityServer::tool(GetSimilarRemediationsTool::class, [
        'rule_key' => 'color-contrast',
        'wcag_criteria' => '1.4.3',
    ])->assertOk()
        ->assertSee('"total":0');
});

it('respects the limit parameter', function (): void {
    for ($i = 1; $i <= 5; $i++) {
        RemediationEmbedding::create([
            'rule_key' => "rule-{$i}",
            'wcag_criteria' => '1.1.1',
            'description' => "Description {$i}",
            'resolution' => "Resolution {$i}",
            'outcome' => 'resolved',
            'embedding' => [1.0, 0.0],
        ]);
    }

    PropertyAccessibilityServer::tool(GetSimilarRemediationsTool::class, [
        'rule_key' => 'rule-1',
        'wcag_criteria' => '1.1.1',
        'limit' => 2,
    ])->assertOk()
        ->assertSee('"total":2');
});
