<?php

use App\Domain\Scans\ScanPage as ScanPageDomain;
use App\Enums\ScanPageStatus;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\ScanPage;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->for($this->agency)->for($this->organization)->create();

    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($user);

    $this->domain = new ScanPageDomain;
    $this->scan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->create();
});

it('records a successfully scanned page', function (): void {
    $page = $this->domain->record($this->scan, 'https://example.com/page', 3);

    expect($page)->toBeInstanceOf(ScanPage::class)
        ->and($page->url)->toBe('https://example.com/page')
        ->and($page->violations_count)->toBe(3)
        ->and($page->status)->toBe(ScanPageStatus::Scanned)
        ->and($page->axe_completed)->toBeTrue()
        ->and($page->scan_id)->toBe($this->scan->id)
        ->and($page->agency_id)->toBe($this->agency->id);
});

it('records a page with zero violations', function (): void {
    $page = $this->domain->record($this->scan, 'https://example.com/clean', 0);

    expect($page->violations_count)->toBe(0)
        ->and($page->status)->toBe(ScanPageStatus::Scanned);
});

it('can record a page by scan id', function (): void {
    $page = $this->domain->record($this->scan->id, 'https://example.com/by-id', 1);

    expect($page->scan_id)->toBe($this->scan->id)
        ->and($page->status)->toBe(ScanPageStatus::Scanned);
});

it('records a failed page', function (): void {
    $page = $this->domain->fail($this->scan, 'https://example.com/error');

    expect($page)->toBeInstanceOf(ScanPage::class)
        ->and($page->url)->toBe('https://example.com/error')
        ->and($page->violations_count)->toBe(0)
        ->and($page->status)->toBe(ScanPageStatus::Failed)
        ->and($page->axe_completed)->toBeTrue()
        ->and($page->scan_id)->toBe($this->scan->id)
        ->and($page->agency_id)->toBe($this->agency->id);
});

it('can fail a page by scan id', function (): void {
    $page = $this->domain->fail($this->scan->id, 'https://example.com/fail');

    expect($page->status)->toBe(ScanPageStatus::Failed);
});

it('persists page records to the database', function (): void {
    $this->domain->record($this->scan, 'https://example.com/persist', 5);
    $this->domain->fail($this->scan, 'https://example.com/fail');

    expect(ScanPage::query()->count())->toBe(2);
});

it('associates pages with the correct scan via the relationship', function (): void {
    $this->domain->record($this->scan, 'https://example.com/a', 2);
    $this->domain->record($this->scan, 'https://example.com/b', 0);

    expect($this->scan->scanPages()->count())->toBe(2);
});

it('updates an existing Pending stub rather than creating a duplicate', function (): void {
    ScanPage::withoutGlobalScopes()->create([
        'agency_id' => $this->agency->id,
        'scan_id' => $this->scan->id,
        'url' => 'https://example.com/stub',
        'violations_count' => 0,
        'status' => ScanPageStatus::Pending,
        'axe_completed' => false,
    ]);

    $this->domain->record($this->scan, 'https://example.com/stub', 4);

    expect(ScanPage::withoutGlobalScopes()->where('scan_id', $this->scan->id)->where('url', 'https://example.com/stub')->count())->toBe(1)
        ->and(ScanPage::withoutGlobalScopes()->where('url', 'https://example.com/stub')->first()->status)->toBe(ScanPageStatus::Scanned)
        ->and(ScanPage::withoutGlobalScopes()->where('url', 'https://example.com/stub')->first()->violations_count)->toBe(4);
});
