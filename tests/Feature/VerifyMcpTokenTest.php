<?php

use App\Enums\ApiKeyScope;
use App\Models\Agency;
use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Register a throwaway route protected by mcp.auth for testing.
    Route::middleware('mcp.auth')->get('/_test/mcp', fn () => response()->json(['ok' => true]));

    $this->agency = Agency::factory()->create();
});

// ── Modern api_keys path ──────────────────────────────────────────────────────

it('authenticates via api_keys token hash (mcp scope)', function (): void {
    $token = ApiKey::generateToken();
    ApiKey::factory()->create([
        'agency_id' => $this->agency->id,
        'token_hash' => $token['hash'],
        'key_prefix' => $token['prefix'],
        'scopes' => [ApiKeyScope::Mcp->value],
    ]);

    $this->withToken($token['plaintext'])
        ->getJson('/_test/mcp')
        ->assertOk();
});

it('rejects an api_keys token with wrong scope', function (): void {
    $token = ApiKey::generateToken();
    ApiKey::factory()->create([
        'agency_id' => $this->agency->id,
        'token_hash' => $token['hash'],
        'key_prefix' => $token['prefix'],
        'scopes' => [ApiKeyScope::ScansRead->value],
    ]);

    $this->withToken($token['plaintext'])
        ->getJson('/_test/mcp')
        ->assertUnauthorized();
});

// ── Legacy mcp_token_hash path ────────────────────────────────────────────────

it('authenticates via the legacy mcp_token_hash column', function (): void {
    $token = 'legacy-plain-text-token';
    $this->agency->forceFill(['mcp_token_hash' => hash('sha256', $token)])->save();

    $this->withToken($token)
        ->getJson('/_test/mcp')
        ->assertOk();
});

it('rejects a mismatched legacy token', function (): void {
    $token = 'correct-token';
    $this->agency->forceFill(['mcp_token_hash' => hash('sha256', $token)])->save();

    $this->withToken('wrong-token')
        ->getJson('/_test/mcp')
        ->assertUnauthorized();
});

it('rejects a request with no bearer token', function (): void {
    $this->getJson('/_test/mcp')
        ->assertUnauthorized();
});

// ── Plain-text token is rejected (security regression guard) ─────────────────

it('does not accept a plain-text token stored directly in the hash column', function (): void {
    // If someone accidentally stored a plain-text value in mcp_token_hash,
    // a request with that same value as the bearer token must still fail
    // because the middleware always hashes the incoming token before comparing.
    $this->agency->forceFill(['mcp_token_hash' => 'not-a-hash'])->save();

    $this->withToken('not-a-hash')
        ->getJson('/_test/mcp')
        ->assertUnauthorized();
});
