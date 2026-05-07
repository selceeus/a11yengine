<?php

use App\Enums\MessagingPlatform;
use App\Jobs\SendWebhookNotificationJob;
use App\Rules\NotPrivateUrl;
use App\Services\IpSafetyChecker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

it('SendWebhookNotificationJob blocks private IPs at dispatch time', function (): void {
    Http::fake();
    Log::spy();

    $checker = Mockery::mock(IpSafetyChecker::class);
    $checker->shouldReceive('isSafe')
        ->once()
        ->andReturnUsing(function (string $url, ?string &$reason = null): bool {
            $reason = 'IP address 192.168.1.1 is in a private or reserved range.';

            return false;
        });

    app()->instance(IpSafetyChecker::class, $checker);

    $job = new SendWebhookNotificationJob(
        url: 'http://192.168.1.1/hook',
        platform: MessagingPlatform::Slack,
        title: 'Test',
        body: 'Body',
    );

    expect(fn () => $job->handle($checker))->not->toThrow(\Throwable::class);

    Http::assertNothingSent();
});
