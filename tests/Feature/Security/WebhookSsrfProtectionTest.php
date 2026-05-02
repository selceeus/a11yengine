<?php

use App\Rules\NotPrivateUrl;
use Illuminate\Support\Facades\Validator;

it('rejects loopback IP addresses', function (): void {
    $validator = Validator::make(
        ['webhook_url' => 'http://127.0.0.1/hook'],
        ['webhook_url' => ['required', 'url', new NotPrivateUrl]]
    );

    expect($validator->fails())->toBeTrue();
});

it('rejects private RFC 1918 IP addresses', function (): void {
    $validator = Validator::make(
        ['webhook_url' => 'http://192.168.1.1/hook'],
        ['webhook_url' => ['required', 'url', new NotPrivateUrl]]
    );

    expect($validator->fails())->toBeTrue();
});

it('rejects 10.x.x.x private IP addresses', function (): void {
    $validator = Validator::make(
        ['webhook_url' => 'http://10.0.0.1/hook'],
        ['webhook_url' => ['required', 'url', new NotPrivateUrl]]
    );

    expect($validator->fails())->toBeTrue();
});

it('rejects 172.16-31.x.x private IP addresses', function (): void {
    $validator = Validator::make(
        ['webhook_url' => 'http://172.16.0.1/hook'],
        ['webhook_url' => ['required', 'url', new NotPrivateUrl]]
    );

    expect($validator->fails())->toBeTrue();
});

it('accepts publicly routable URLs', function (): void {
    $validator = Validator::make(
        ['webhook_url' => 'https://hooks.slack.com/services/abc123'],
        ['webhook_url' => ['required', 'url', new NotPrivateUrl]]
    );

    expect($validator->fails())->toBeFalse();
});
