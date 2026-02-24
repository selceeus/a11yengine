<?php

use App\Models\Agency;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates an agency with the factory', function (): void {
    $agency = Agency::factory()->create();

    expect($agency->id)->not->toBeNull()
        ->and($agency->name)->not->toBe('')
        ->and($agency->slug)->not->toBe('');

    $this->assertDatabaseHas('agencies', [
        'id' => $agency->id,
        'name' => $agency->name,
        'slug' => $agency->slug,
    ]);
});

it('supports a nullable billing email state', function (): void {
    $agency = Agency::factory()->withoutBillingEmail()->create();

    expect($agency->billing_email)->toBeNull();
});
