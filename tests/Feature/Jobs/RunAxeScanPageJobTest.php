<?php

use App\Domain\Issues\ProcessHtmlScan;
use App\Enums\ScanPageStatus;
use App\Jobs\RunAxeScanPageJob;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\ScanPage;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->create(['base_url' => 'https://example.com']);

    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($user);

    $this->scan = Scan::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->create();

    // Pre-create a Pending stub as ScanPageDispatcher would
    $this->pageUrl = 'https://example.com/page';
    $this->stub = ScanPage::withoutGlobalScopes()->create([
        'agency_id' => $this->agency->id,
        'scan_id' => $this->scan->id,
        'url' => $this->pageUrl,
        'violations_count' => 0,
        'status' => ScanPageStatus::Pending,
        'axe_completed' => false,
        'lighthouse_completed' => null,
    ]);
});

// ─── Queue dispatching ────────────────────────────────────────────────────────

it('can be dispatched to the queue', function (): void {
    Queue::fake();

    RunAxeScanPageJob::dispatch($this->scan, $this->pageUrl, []);

    Queue::assertPushed(
        RunAxeScanPageJob::class,
        fn ($job) => $job->scan->is($this->scan) && $job->url === $this->pageUrl,
    );
});

// ─── Processing ──────────────────────────────────────────────────────────────

it('updates the ScanPage stub to Scanned status on success', function (): void {
    (new RunAxeScanPageJob($this->scan, $this->pageUrl, []))->handle(app(ProcessHtmlScan::class));

    expect($this->stub->fresh()->status)->toBe(ScanPageStatus::Scanned);
});

it('sets axe_completed=true on the stub after processing', function (): void {
    (new RunAxeScanPageJob($this->scan, $this->pageUrl, []))->handle(app(ProcessHtmlScan::class));

    expect($this->stub->fresh()->axe_completed)->toBeTrue();
});

it('records the correct violations_count on the stub', function (): void {
    $violations = [
        ['id' => 'image-alt', 'impact' => 'critical', 'nodes' => [
            ['target' => ['#a'], 'failureSummary' => 'Fix.'],
            ['target' => ['#b'], 'failureSummary' => 'Fix.'],
        ]],
    ];

    (new RunAxeScanPageJob($this->scan, $this->pageUrl, $violations))->handle(app(ProcessHtmlScan::class));

    expect($this->stub->fresh()->violations_count)->toBe(2);
});

it('does not create a duplicate ScanPage record — updates the existing stub', function (): void {
    (new RunAxeScanPageJob($this->scan, $this->pageUrl, []))->handle(app(ProcessHtmlScan::class));

    expect(ScanPage::withoutGlobalScopes()->where('scan_id', $this->scan->id)->count())->toBe(1);
});

// ─── Failed hook ─────────────────────────────────────────────────────────────

it('marks the stub as Failed with axe_completed=true when the job fails', function (): void {
    $job = new RunAxeScanPageJob($this->scan, $this->pageUrl, []);
    $job->failed(new RuntimeException('Axe processing error'));

    expect($this->stub->fresh()->status)->toBe(ScanPageStatus::Failed)
        ->and($this->stub->fresh()->axe_completed)->toBeTrue();
});

it('does not overwrite a Scanned page when failed() is called', function (): void {
    // Simulate a partial success where record() was already called
    $this->stub->update(['status' => ScanPageStatus::Scanned, 'axe_completed' => true, 'violations_count' => 5]);

    $job = new RunAxeScanPageJob($this->scan, $this->pageUrl, []);
    $job->failed(new RuntimeException('Late failure'));

    expect($this->stub->fresh()->status)->toBe(ScanPageStatus::Scanned)
        ->and($this->stub->fresh()->violations_count)->toBe(5);
});
