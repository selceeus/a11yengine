<?php

use App\Enums\AuditStatus;
use App\Jobs\GenerateAiAuditJob;
use App\Models\Agency;
use App\Models\Audit;
use App\Models\Organization;
use App\Models\Property;
use Illuminate\Support\Facades\Http;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

/** Build a fake OpenAI chat/completions JSON response body. */
function fakeOpenAiResponse(mixed $content): array
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

    $this->audit = Audit::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->create();

    config(['ai.driver' => 'openai']);
    config(['ai.providers.openai.api_key' => 'test-key']);
});

// ─── status transitions ───────────────────────────────────────────────────────

it('transitions status from Pending through Processing to Completed on success', function (): void {
    $aiJson = json_encode([
        'overall_score' => 78,
        'executive_summary' => 'Good accessibility.',
        'compliance_status' => ['wcag_a' => 'pass', 'wcag_aa' => 'partial', 'wcag_aaa' => 'fail'],
        'summary_statistics' => ['total_issues' => 10],
        'top_risks' => [],
        'issue_details' => [],
        'remediations' => [],
    ]);

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response(
            fakeOpenAiResponse($aiJson),
            200,
        ),
    ]);

    (new GenerateAiAuditJob($this->audit))->handle(app(\App\Services\AiAuditService::class));

    $fresh = $this->audit->fresh();
    expect($fresh->status)->toBe(AuditStatus::Completed)
        ->and($fresh->overall_score)->toBe(78)
        ->and($fresh->generated_at)->not->toBeNull();
});

it('populates executive_summary from AI response', function (): void {
    $aiJson = json_encode([
        'overall_score' => 60,
        'executive_summary' => 'Needs improvement.',
        'compliance_status' => [],
        'summary_statistics' => [],
        'top_risks' => [],
        'issue_details' => [],
        'remediations' => [],
    ]);

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response(
            fakeOpenAiResponse($aiJson),
            200,
        ),
    ]);

    (new GenerateAiAuditJob($this->audit))->handle(app(\App\Services\AiAuditService::class));

    expect($this->audit->fresh()->executive_summary)->toBe('Needs improvement.');
});

// ─── failure handling ─────────────────────────────────────────────────────────

it('sets Failed status and records error_message when the job fails', function (): void {
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([], 500),
    ]);

    $job = new GenerateAiAuditJob($this->audit);
    $job->failed(new \RuntimeException('OpenAI returned HTTP 500'));

    $fresh = $this->audit->fresh();
    expect($fresh->status)->toBe(AuditStatus::Failed)
        ->and($fresh->error_message)->toContain('OpenAI returned HTTP 500');
});

it('truncates long error messages to 250 characters', function (): void {
    $longMessage = str_repeat('x', 300);

    (new GenerateAiAuditJob($this->audit))->failed(new \RuntimeException($longMessage));

    expect(strlen($this->audit->fresh()->error_message))->toBe(250);
});

// ─── queue dispatch ───────────────────────────────────────────────────────────

it('can be pushed to the queue', function (): void {
    \Illuminate\Support\Facades\Queue::fake();

    GenerateAiAuditJob::dispatch($this->audit);

    \Illuminate\Support\Facades\Queue::assertPushed(
        GenerateAiAuditJob::class,
        fn ($job) => $job->audit->is($this->audit),
    );
});
