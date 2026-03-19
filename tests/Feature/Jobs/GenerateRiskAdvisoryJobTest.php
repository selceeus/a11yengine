<?php

use App\Domain\Risk\AiRiskAdvisorService;
use App\Enums\RiskAdvisoryStatus;
use App\Jobs\GenerateRiskAdvisoryJob;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\RiskAdvisory;
use Illuminate\Support\Facades\Http;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

/** Build a fake OpenAI chat/completions JSON response body. */
function fakeAdvisoryOpenAiResponse(mixed $content): array
{
    return [
        'choices' => [
            ['message' => ['content' => is_string($content) ? $content : json_encode($content)]],
        ],
    ];
}

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->for($this->agency)->for($this->organization)->create();

    $this->advisory = RiskAdvisory::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
    ]);

    config(['ai.driver' => 'openai']);
    config(['ai.providers.openai.api_key' => 'test-key']);
});

// ── Success ───────────────────────────────────────────────────────────────────

it('transitions to Completed with priorities on a successful AI response', function (): void {
    $aiJson = json_encode([
        'priorities' => [
            [
                'rank' => 1,
                'issue_id' => 1,
                'title' => 'Images Missing Alt Text',
                'rule_key' => 'image-alt',
                'severity' => 'critical',
                'risk_reduction_score' => 85,
                'ease_of_remediation' => 'easy',
                'user_impact' => 'high',
                'compliance_importance' => 'high',
                'affected_pages' => 10,
                'affected_page_urls' => ['/products'],
                'quick_win' => true,
                'rationale' => 'High-traffic page missing alt text on all product images.',
            ],
        ],
    ]);

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response(
            fakeAdvisoryOpenAiResponse($aiJson),
            200,
        ),
    ]);

    (new GenerateRiskAdvisoryJob($this->advisory))->handle(app(AiRiskAdvisorService::class));

    $fresh = $this->advisory->fresh();
    expect($fresh->status)->toBe(RiskAdvisoryStatus::Completed)
        ->and($fresh->total_recommendations)->toBe(1)
        ->and($fresh->priorities)->toHaveCount(1)
        ->and($fresh->generated_at)->not->toBeNull();
});

it('stores an empty priorities array when AI returns no priorities', function (): void {
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response(
            fakeAdvisoryOpenAiResponse(json_encode(['priorities' => []])),
            200,
        ),
    ]);

    (new GenerateRiskAdvisoryJob($this->advisory))->handle(app(AiRiskAdvisorService::class));

    $fresh = $this->advisory->fresh();
    expect($fresh->status)->toBe(RiskAdvisoryStatus::Completed)
        ->and($fresh->total_recommendations)->toBe(0)
        ->and($fresh->priorities)->toBe([]);
});

it('includes traffic_score in the prompt context', function (): void {
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response(
            fakeAdvisoryOpenAiResponse(json_encode(['priorities' => []])),
            200,
        ),
    ]);

    (new GenerateRiskAdvisoryJob($this->advisory))->handle(app(AiRiskAdvisorService::class));

    $recorded = Http::recorded();
    expect($recorded)->not->toBeEmpty();

    $requestBody = (string) $recorded[0][0]->body();
    expect($requestBody)->toContain('traffic_score');
});

// ── Failure ───────────────────────────────────────────────────────────────────

it('transitions to Failed when the job fails', function (): void {
    (new GenerateRiskAdvisoryJob($this->advisory))->failed(new RuntimeException('Connection timeout'));

    $fresh = $this->advisory->fresh();
    expect($fresh->status)->toBe(RiskAdvisoryStatus::Failed)
        ->and($fresh->error_message)->toContain('Connection timeout');
});

it('truncates the error message to 250 characters', function (): void {
    $longMessage = str_repeat('x', 300);

    (new GenerateRiskAdvisoryJob($this->advisory))->failed(new RuntimeException($longMessage));

    expect($this->advisory->fresh()->error_message)->toHaveLength(250);
});

// ── Status transitions ────────────────────────────────────────────────────────

it('sets status to Processing before invoking the service', function (): void {
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response(
            fakeAdvisoryOpenAiResponse(json_encode(['priorities' => []])),
            200,
        ),
    ]);

    (new GenerateRiskAdvisoryJob($this->advisory))->handle(app(AiRiskAdvisorService::class));

    // After completion the record must have transitioned all the way to Completed.
    expect($this->advisory->fresh()->status)->toBe(RiskAdvisoryStatus::Completed);
});
