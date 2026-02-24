<?php

use App\Http\Middleware\SetCurrentAgency;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

uses(RefreshDatabase::class);

it('binds the agency to the container for an authenticated user', function (): void {
    $agency = Agency::factory()->create();
    $user = User::factory()->create(['agency_id' => $agency->id]);

    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    $next = fn () => new Response;

    (new SetCurrentAgency)->handle($request, $next);

    expect(app(Agency::class)->id)->toBe($agency->id);
});

it('skips binding when user has no agency_id', function (): void {
    $user = User::factory()->create(['agency_id' => null]);

    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    $next = fn () => new Response('ok');

    $response = (new SetCurrentAgency)->handle($request, $next);

    expect($response->getContent())->toBe('ok');
});

it('skips binding when user is not authenticated', function (): void {
    $request = Request::create('/');

    $next = fn () => new Response('ok');

    $response = (new SetCurrentAgency)->handle($request, $next);

    expect($response->getContent())->toBe('ok');
});
