<?php

use App\Ai\Agents\ContentAuditAgent;
use App\Domain\Content\AiContentAuditService;
use App\Enums\ContentAuditStatus;
use App\Jobs\GenerateContentAuditJob;
use App\Models\Agency;
use App\Models\ContentAudit;
use App\Models\Finding;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Ai;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->for($this->agency)->for($this->organization)->create();

    $this->audit = ContentAudit::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
    ]);
});

// ── Success ───────────────────────────────────────────────────────────────────

it('transitions to Completed with content_issues on a successful AI response', function (): void {
    $pageUrl = 'https://test.example.com/about';

    $scan = Scan::factory()->completed()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
    ]);

    Finding::factory()->create([
        'agency_id' => $this->agency->id,
        'scan_id' => $scan->id,
        'property_id' => $this->property->id,
        'rule_key' => 'image-alt',
        'page_url' => $pageUrl,
    ]);

    $aiJson = json_encode([
        'content_issues' => [
            [
                'page_url' => $pageUrl,
                'issue_id' => null,
                'rule_key' => 'image-alt',
                'category' => 'alt_text',
                'issue_type' => 'Missing alt text',
                'element_html' => '<img src="photo.jpg">',
                'current_text' => null,
                'issue' => 'The image has no alt text.',
                'suggestion' => 'Add a descriptive alt attribute.',
                'severity' => 'critical',
                'wcag_criteria' => '1.1.1',
                'writer_note' => 'Describe what the image conveys.',
                'developer_note' => 'Add alt="" attribute.',
            ],
        ],
        'reading_metrics' => [
            [
                'page_url' => $pageUrl,
                'reading_level' => 'Grade 8 (Flesch-Kincaid)',
                'reading_time' => '1 min',
                'reading_time_seconds' => 60,
                'word_count' => 230,
                'flesch_score' => 63.5,
            ],
        ],
    ]);

    Ai::fakeAgent(ContentAuditAgent::class, [json_decode($aiJson, true)]);
    Http::fake([
        $pageUrl => Http::response('<html><body><img src="photo.jpg"></body></html>', 200),
    ]);

    (new GenerateContentAuditJob($this->audit))->handle(app(AiContentAuditService::class));

    $fresh = $this->audit->fresh();
    expect($fresh->status)->toBe(ContentAuditStatus::Completed)
        ->and($fresh->total_issues)->toBe(1)
        ->and($fresh->content_issues)->toHaveCount(1)
        ->and($fresh->reading_metrics)->toHaveCount(1)
        ->and($fresh->reading_metrics[0]['reading_level'])->toBe('Grade 8 (Flesch-Kincaid)')
        ->and($fresh->avg_reading_level)->toBe('Grade 8 (Flesch-Kincaid)')
        ->and($fresh->avg_reading_time_seconds)->toBe(60)
        ->and($fresh->generated_at)->not->toBeNull();
});

it('stores an empty content_issues array when AI returns none', function (): void {
    Ai::fakeAgent(ContentAuditAgent::class, [['content_issues' => [], 'reading_metrics' => []]]);

    (new GenerateContentAuditJob($this->audit))->handle(app(AiContentAuditService::class));

    $fresh = $this->audit->fresh();
    expect($fresh->status)->toBe(ContentAuditStatus::Completed)
        ->and($fresh->total_issues)->toBe(0)
        ->and($fresh->content_issues)->toBe([])
        ->and($fresh->reading_metrics)->toBe([])
        ->and($fresh->avg_reading_level)->toBeNull()
        ->and($fresh->avg_reading_time_seconds)->toBeNull();
});

it('fetches HTML for discovered pages and stores it in the prompt context', function (): void {
    $pageUrl = 'https://test.example.com/products';

    $scan = Scan::factory()->completed()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
    ]);

    Finding::factory()->create([
        'agency_id' => $this->agency->id,
        'scan_id' => $scan->id,
        'property_id' => $this->property->id,
        'rule_key' => 'link-name',
        'page_url' => $pageUrl,
    ]);

    Ai::fakeAgent(ContentAuditAgent::class, [['content_issues' => [], 'reading_metrics' => []]]);
    Http::fake([
        $pageUrl => Http::response('<html><body><a href="#">Click here</a></body></html>', 200),
    ]);

    (new GenerateContentAuditJob($this->audit))->handle(app(AiContentAuditService::class));

    $recorded = Http::recorded();
    $urls = collect($recorded)->map(fn ($pair) => (string) $pair[0]->url())->all();

    expect($urls)->toContain($pageUrl);
});

// ── Failure ───────────────────────────────────────────────────────────────────

it('transitions to Failed when the job fails', function (): void {
    (new GenerateContentAuditJob($this->audit))->failed(new RuntimeException('Connection timeout'));

    $fresh = $this->audit->fresh();
    expect($fresh->status)->toBe(ContentAuditStatus::Failed)
        ->and($fresh->error_message)->toContain('Connection timeout');
});

it('truncates the error message to 250 characters', function (): void {
    $longMessage = str_repeat('x', 300);

    (new GenerateContentAuditJob($this->audit))->failed(new RuntimeException($longMessage));

    expect($this->audit->fresh()->error_message)->toHaveLength(250);
});

// ── Status transitions ─────────────────────────────────────────────────────────

it('transitions through Processing to Completed on a successful run', function (): void {
    Ai::fakeAgent(ContentAuditAgent::class, [['content_issues' => [], 'reading_metrics' => []]]);

    (new GenerateContentAuditJob($this->audit))->handle(app(AiContentAuditService::class));

    expect($this->audit->fresh()->status)->toBe(ContentAuditStatus::Completed);
});

// ── Reading metrics ───────────────────────────────────────────────────────────

it('computes avg_reading_level and avg_reading_time_seconds from multi-page reading_metrics', function (): void {
    $metrics = [
        ['page_url' => '/a', 'reading_level' => 'Grade 8 (Flesch-Kincaid)', 'reading_time' => '1 min', 'reading_time_seconds' => 60, 'word_count' => 230, 'flesch_score' => 65.0],
        ['page_url' => '/b', 'reading_level' => 'Grade 12 (Flesch-Kincaid)', 'reading_time' => '2 min', 'reading_time_seconds' => 120, 'word_count' => 460, 'flesch_score' => 40.0],
    ];

    Ai::fakeAgent(ContentAuditAgent::class, [['content_issues' => [], 'reading_metrics' => $metrics]]);

    (new GenerateContentAuditJob($this->audit))->handle(app(AiContentAuditService::class));

    $fresh = $this->audit->fresh();
    // avg grade = (8+12)/2 = 10
    expect($fresh->avg_reading_level)->toBe('Grade 10 (Flesch-Kincaid)')
        // avg time = (60+120)/2 = 90
        ->and($fresh->avg_reading_time_seconds)->toBe(90)
        ->and($fresh->reading_metrics)->toHaveCount(2);
});

it('formats reading time correctly via the service helper', function (): void {
    $service = app(AiContentAuditService::class);

    expect($service->formatReadingTime(90))->toBe('1 min 30 sec')
        ->and($service->formatReadingTime(120))->toBe('2 min')
        ->and($service->formatReadingTime(45))->toBe('45 sec')
        ->and($service->formatReadingTime(0))->toBe('0 sec');
});
