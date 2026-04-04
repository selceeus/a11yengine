<?php

namespace App\Http\Controllers\Settings;

use App\Enums\MessagingPlatform;
use App\Enums\NotificationEmailCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreNotificationWebhookRouteRequest;
use App\Models\NotificationWebhookRoute;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationWebhookRouteController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $agencyId = $request->user()->agency_id;

        $routes = NotificationWebhookRoute::query()
            ->where('agency_id', $agencyId)
            ->orderBy('category')
            ->orderBy('platform')
            ->get(['id', 'category', 'platform', 'webhook_url', 'label'])
            ->map(fn (NotificationWebhookRoute $route) => [
                'id' => $route->id,
                'category' => $route->category->value,
                'platform' => $route->platform->value,
                'platform_label' => $route->platform->label(),
                'webhook_url_masked' => $this->maskUrl($route->webhook_url),
                'label' => $route->label,
            ]);

        $categories = collect(NotificationEmailCategory::cases())->map(fn (NotificationEmailCategory $cat) => [
            'value' => $cat->value,
            'label' => $cat->label(),
            'description' => $cat->description(),
        ]);

        $platforms = collect(MessagingPlatform::cases())->map(fn (MessagingPlatform $p) => [
            'value' => $p->value,
            'label' => $p->label(),
        ]);

        return Inertia::render('settings/notification-webhook-routes', [
            'routes' => $routes,
            'categories' => $categories,
            'platforms' => $platforms,
        ]);
    }

    public function store(StoreNotificationWebhookRouteRequest $request): RedirectResponse
    {
        $agencyId = $request->user()->agency_id;

        NotificationWebhookRoute::firstOrCreate(
            [
                'agency_id' => $agencyId,
                'category' => $request->validated('category'),
                'platform' => $request->validated('platform'),
                'webhook_url' => $request->validated('webhook_url'),
            ],
            [
                'label' => $request->validated('label'),
            ],
        );

        return redirect()->route('notification-webhook-routes.index');
    }

    public function destroy(Request $request, NotificationWebhookRoute $notificationWebhookRoute): RedirectResponse
    {
        $this->authorize('update', $notificationWebhookRoute->agency);

        $notificationWebhookRoute->delete();

        return redirect()->route('notification-webhook-routes.index');
    }

    private function maskUrl(string $url): string
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '...';
        $path = $parsed['path'] ?? '';
        $segments = array_filter(explode('/', $path));
        $visibleParts = array_slice($segments, 0, 2);
        $maskedParts = array_map(fn () => '***', array_slice($segments, 2));

        return ($parsed['scheme'] ?? 'https').'://'.$host.'/'.implode('/', [...$visibleParts, ...$maskedParts]);
    }
}
