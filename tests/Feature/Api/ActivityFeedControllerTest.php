<?php

use App\Models\ActivityLog;
use App\Models\User;

// ── Cursor pagination (default) ───────────────────────────────────────────────

test('guests cannot access the activity feed', function (): void {
    $this->getJson(route('api.activity-feed'))
        ->assertUnauthorized();
});

test('it returns paginated activity entries with a next_cursor key', function (): void {
    $user = User::factory()->create();
    ActivityLog::factory()->count(3)->create();

    $this->actingAs($user)
        ->getJson(route('api.activity-feed'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'event', 'event_label', 'event_category', 'actor_label', 'created_at']],
            'next_cursor',
        ]);
});

test('entries are returned newest first by default', function (): void {
    $user = User::factory()->create();
    $first = ActivityLog::factory()->create(['created_at' => now()->subHours(2)]);
    $second = ActivityLog::factory()->create(['created_at' => now()->subHour()]);
    $third = ActivityLog::factory()->create(['created_at' => now()]);

    $response = $this->actingAs($user)
        ->getJson(route('api.activity-feed'))
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids[0])->toBe($third->id)
        ->and($ids[1])->toBe($second->id)
        ->and($ids[2])->toBe($first->id);
});

// ── after_id polling ─────────────────────────────────────────────────────────

test('after_id returns only entries newer than the given id', function (): void {
    $user = User::factory()->create();
    $old = ActivityLog::factory()->create(['created_at' => now()->subHour()]);
    $new = ActivityLog::factory()->create(['created_at' => now()]);

    $response = $this->actingAs($user)
        ->getJson(route('api.activity-feed', ['after_id' => $old->id]))
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($new->id)
        ->and($ids)->not->toContain($old->id);
});

test('after_id returns entries ordered ascending', function (): void {
    $user = User::factory()->create();
    $first = ActivityLog::factory()->create(['created_at' => now()->subMinutes(5)]);
    $second = ActivityLog::factory()->create(['created_at' => now()->subMinutes(3)]);
    $third = ActivityLog::factory()->create(['created_at' => now()]);

    $response = $this->actingAs($user)
        ->getJson(route('api.activity-feed', ['after_id' => $first->id]))
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids[0])->toBe($second->id)
        ->and($ids[1])->toBe($third->id);
});

test('after_id returns next_cursor as null', function (): void {
    $user = User::factory()->create();
    $log = ActivityLog::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.activity-feed', ['after_id' => $log->id - 1]))
        ->assertOk()
        ->assertJsonPath('next_cursor', null);
});

test('after_id returns empty data array when no newer entries exist', function (): void {
    $user = User::factory()->create();
    $log = ActivityLog::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.activity-feed', ['after_id' => $log->id]))
        ->assertOk()
        ->assertJsonPath('data', []);
});
