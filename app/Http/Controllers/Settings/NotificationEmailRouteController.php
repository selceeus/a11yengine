<?php

namespace App\Http\Controllers\Settings;

use App\Enums\NotificationEmailCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreNotificationEmailRouteRequest;
use App\Models\NotificationEmailRoute;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationEmailRouteController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $agencyId = $request->user()->agency_id;

        $routes = NotificationEmailRoute::query()
            ->where('agency_id', $agencyId)
            ->orderBy('category')
            ->orderBy('email')
            ->get(['id', 'category', 'email', 'label'])
            ->map(fn (NotificationEmailRoute $route) => [
                'id' => $route->id,
                'category' => $route->category->value,
                'email' => $route->email,
                'label' => $route->label,
            ]);

        $categories = collect(NotificationEmailCategory::cases())->map(fn (NotificationEmailCategory $cat) => [
            'value' => $cat->value,
            'label' => $cat->label(),
            'description' => $cat->description(),
        ]);

        return Inertia::render('settings/notification-email-routes', [
            'routes' => $routes,
            'categories' => $categories,
        ]);
    }

    public function store(StoreNotificationEmailRouteRequest $request): RedirectResponse
    {
        $agencyId = $request->user()->agency_id;

        NotificationEmailRoute::firstOrCreate(
            [
                'agency_id' => $agencyId,
                'category' => $request->validated('category'),
                'email' => $request->validated('email'),
            ],
            [
                'label' => $request->validated('label'),
            ],
        );

        return redirect()->route('notification-email-routes.index');
    }

    public function destroy(Request $request, NotificationEmailRoute $notificationEmailRoute): RedirectResponse
    {
        $this->authorize('update', $notificationEmailRoute->agency);

        $notificationEmailRoute->delete();

        return redirect()->route('notification-email-routes.index');
    }
}
