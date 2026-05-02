<?php

use App\Domain\Integrations\Providers\AzureDevOpsProvider;
use App\Domain\Integrations\Providers\BasecampProvider;
use App\Domain\Integrations\Providers\ClickUpProvider;
use App\Domain\Integrations\Providers\LinearProvider;
use App\Domain\Integrations\Providers\TrelloProvider;
use App\Enums\IntegrationProvider;
use App\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

// ── Linear ────────────────────────────────────────────────────────────────────

describe('LinearProvider webhook verification', function (): void {
    it('passes a valid HMAC-SHA256 signature', function (): void {
        $secret = 'linear-secret';
        $body = '{"action":"create"}';

        $integration = Integration::factory()->make([
            'provider' => IntegrationProvider::Linear,
            'credentials' => ['api_key' => 'key', 'team_id' => 'team', 'webhook_secret' => $secret],
        ]);

        $signature = hash_hmac('sha256', $body, $secret);

        $request = Request::create('/webhook', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Linear-Signature', $signature);

        expect((new LinearProvider)->verifyWebhook($integration, $request))->toBeTrue();
    });

    it('rejects an invalid signature', function (): void {
        $integration = Integration::factory()->make([
            'provider' => IntegrationProvider::Linear,
            'credentials' => ['webhook_secret' => 'real-secret'],
        ]);

        $request = Request::create('/webhook', 'POST', [], [], [], [], '{"x":1}');
        $request->headers->set('X-Linear-Signature', 'bad-signature');

        expect((new LinearProvider)->verifyWebhook($integration, $request))->toBeFalse();
    });

    it('rejects when no webhook_secret is configured', function (): void {
        $integration = Integration::factory()->make([
            'provider' => IntegrationProvider::Linear,
            'credentials' => ['api_key' => 'key', 'team_id' => 'team'],
        ]);

        $request = Request::create('/webhook', 'POST', [], [], [], [], '{}');

        expect((new LinearProvider)->verifyWebhook($integration, $request))->toBeFalse();
    });
});

// ── ClickUp ───────────────────────────────────────────────────────────────────

describe('ClickUpProvider webhook verification', function (): void {
    it('passes a valid HMAC-SHA256 signature', function (): void {
        $secret = 'clickup-secret';
        $body = '{"event":"taskCreated"}';

        $integration = Integration::factory()->make([
            'provider' => IntegrationProvider::ClickUp,
            'credentials' => ['api_token' => 'tok', 'list_id' => '1', 'webhook_secret' => $secret],
        ]);

        $signature = hash_hmac('sha256', $body, $secret);

        $request = Request::create('/webhook', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Signature', $signature);

        expect((new ClickUpProvider)->verifyWebhook($integration, $request))->toBeTrue();
    });

    it('rejects an invalid signature', function (): void {
        $integration = Integration::factory()->make([
            'provider' => IntegrationProvider::ClickUp,
            'credentials' => ['webhook_secret' => 'real-secret'],
        ]);

        $request = Request::create('/webhook', 'POST', [], [], [], [], '{}');
        $request->headers->set('X-Signature', 'wrong');

        expect((new ClickUpProvider)->verifyWebhook($integration, $request))->toBeFalse();
    });

    it('rejects when no webhook_secret is configured', function (): void {
        $integration = Integration::factory()->make([
            'provider' => IntegrationProvider::ClickUp,
            'credentials' => ['api_token' => 'tok', 'list_id' => '1'],
        ]);

        $request = Request::create('/webhook', 'POST', [], [], [], [], '{}');

        expect((new ClickUpProvider)->verifyWebhook($integration, $request))->toBeFalse();
    });
});

// ── Basecamp ──────────────────────────────────────────────────────────────────

describe('BasecampProvider webhook verification', function (): void {
    it('passes a valid sha256= prefixed signature', function (): void {
        $secret = 'basecamp-secret';
        $body = '{"kind":"todo_created"}';

        $integration = Integration::factory()->make([
            'provider' => IntegrationProvider::Basecamp,
            'credentials' => ['access_token' => 'tok', 'account_id' => '1', 'project_id' => '2', 'webhook_secret' => $secret],
        ]);

        $signature = 'sha256='.hash_hmac('sha256', $body, $secret);

        $request = Request::create('/webhook', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Signature-256', $signature);

        expect((new BasecampProvider)->verifyWebhook($integration, $request))->toBeTrue();
    });

    it('rejects an invalid signature', function (): void {
        $integration = Integration::factory()->make([
            'provider' => IntegrationProvider::Basecamp,
            'credentials' => ['webhook_secret' => 'real-secret'],
        ]);

        $request = Request::create('/webhook', 'POST', [], [], [], [], '{}');
        $request->headers->set('X-Signature-256', 'sha256=badhash');

        expect((new BasecampProvider)->verifyWebhook($integration, $request))->toBeFalse();
    });

    it('rejects when no webhook_secret is configured', function (): void {
        $integration = Integration::factory()->make([
            'provider' => IntegrationProvider::Basecamp,
            'credentials' => ['access_token' => 'tok', 'account_id' => '1', 'project_id' => '2'],
        ]);

        $request = Request::create('/webhook', 'POST', [], [], [], [], '{}');

        expect((new BasecampProvider)->verifyWebhook($integration, $request))->toBeFalse();
    });
});

// ── Trello ────────────────────────────────────────────────────────────────────

describe('TrelloProvider webhook verification', function (): void {
    it('passes a valid HMAC-SHA1 base64 signature', function (): void {
        $secret = 'trello-api-secret';
        $body = '{"action":{"type":"createCard"}}';
        $url = 'https://example.com/webhook';

        $integration = Integration::factory()->make([
            'provider' => IntegrationProvider::Trello,
            'credentials' => ['api_key' => 'key', 'api_secret' => $secret, 'token' => 'tok', 'list_id' => '1'],
        ]);

        $signature = base64_encode(hash_hmac('sha1', $body.$url, $secret, true));

        $request = Request::create($url, 'POST', [], [], [], [], $body);
        $request->headers->set('X-Trello-Webhook', $signature);

        expect((new TrelloProvider)->verifyWebhook($integration, $request))->toBeTrue();
    });

    it('rejects an invalid signature', function (): void {
        $integration = Integration::factory()->make([
            'provider' => IntegrationProvider::Trello,
            'credentials' => ['api_secret' => 'real-secret'],
        ]);

        $request = Request::create('/webhook', 'POST', [], [], [], [], '{}');
        $request->headers->set('X-Trello-Webhook', 'bad==');

        expect((new TrelloProvider)->verifyWebhook($integration, $request))->toBeFalse();
    });

    it('rejects when no api_secret is configured', function (): void {
        $integration = Integration::factory()->make([
            'provider' => IntegrationProvider::Trello,
            'credentials' => ['api_key' => 'key', 'token' => 'tok', 'list_id' => '1'],
        ]);

        $request = Request::create('/webhook', 'POST', [], [], [], [], '{}');

        expect((new TrelloProvider)->verifyWebhook($integration, $request))->toBeFalse();
    });
});

// ── AzureDevOps ───────────────────────────────────────────────────────────────

describe('AzureDevOpsProvider webhook verification', function (): void {
    it('passes a valid Basic auth password', function (): void {
        $password = 'my-webhook-password';

        $integration = Integration::factory()->make([
            'provider' => IntegrationProvider::AzureDevOps,
            'credentials' => ['organization' => 'org', 'project' => 'proj', 'pat' => 'pat', 'webhook_password' => $password],
        ]);

        $encoded = base64_encode('user:'.$password);

        $request = Request::create('/webhook', 'POST', [], [], [], [], '{}');
        $request->headers->set('Authorization', 'Basic '.$encoded);

        expect((new AzureDevOpsProvider)->verifyWebhook($integration, $request))->toBeTrue();
    });

    it('rejects an incorrect password', function (): void {
        $integration = Integration::factory()->make([
            'provider' => IntegrationProvider::AzureDevOps,
            'credentials' => ['webhook_password' => 'real-password'],
        ]);

        $request = Request::create('/webhook', 'POST', [], [], [], [], '{}');
        $request->headers->set('Authorization', 'Basic '.base64_encode('user:wrong-password'));

        expect((new AzureDevOpsProvider)->verifyWebhook($integration, $request))->toBeFalse();
    });

    it('rejects a request with no Authorization header when password is configured', function (): void {
        $integration = Integration::factory()->make([
            'provider' => IntegrationProvider::AzureDevOps,
            'credentials' => ['webhook_password' => 'real-password'],
        ]);

        $request = Request::create('/webhook', 'POST', [], [], [], [], '{}');

        expect((new AzureDevOpsProvider)->verifyWebhook($integration, $request))->toBeFalse();
    });

    it('rejects when no webhook_password is configured', function (): void {
        $integration = Integration::factory()->make([
            'provider' => IntegrationProvider::AzureDevOps,
            'credentials' => ['organization' => 'org', 'project' => 'proj', 'pat' => 'pat'],
        ]);

        $request = Request::create('/webhook', 'POST', [], [], [], [], '{}');

        expect((new AzureDevOpsProvider)->verifyWebhook($integration, $request))->toBeFalse();
    });
});
