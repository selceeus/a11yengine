<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserRolesExportController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        $agencyId = auth()->user()->agency_id;
        $filename = 'user-roles-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($agencyId): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'name',
                'email',
                'role',
                'role_scope',
                'two_factor_enabled',
                'last_login_at',
                'invited_at',
            ]);

            $lastLogins = ActivityLog::withoutGlobalScopes()
                ->where('agency_id', $agencyId)
                ->where('event', 'user.login')
                ->selectRaw('user_id, max(created_at) as last_login_at')
                ->groupBy('user_id')
                ->pluck('last_login_at', 'user_id');

            User::query()
                ->where('agency_id', $agencyId)
                ->with(['roles' => fn ($q) => $q->where('agency_id', $agencyId)->with(['organization:id,name', 'property:id,name'])])
                ->orderBy('name')
                ->chunk(200, function ($users) use ($handle, $lastLogins): void {
                    foreach ($users as $user) {
                        if ($user->roles->isEmpty()) {
                            fputcsv($handle, [
                                $user->name,
                                $user->email,
                                '(no role)',
                                'agency',
                                $user->two_factor_confirmed_at ? 'yes' : 'no',
                                $lastLogins[$user->id] ?? null,
                                $user->created_at->toIso8601String(),
                            ]);
                        } else {
                            foreach ($user->roles as $role) {
                                $scope = match (true) {
                                    $role->property !== null => "property: {$role->property->name}",
                                    $role->organization !== null => "org: {$role->organization->name}",
                                    default => 'agency',
                                };

                                fputcsv($handle, [
                                    $user->name,
                                    $user->email,
                                    $role->role->value,
                                    $scope,
                                    $user->two_factor_confirmed_at ? 'yes' : 'no',
                                    $lastLogins[$user->id] ?? null,
                                    $user->created_at->toIso8601String(),
                                ]);
                            }
                        }
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
