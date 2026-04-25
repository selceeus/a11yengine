<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AccessReview;
use App\Models\ApiKey;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class Soc2EvidenceController extends Controller
{
    public function __invoke(): Response
    {
        $agencyId = auth()->user()->agency_id;

        $userCount = User::where('agency_id', $agencyId)->count();

        $twoFactorCount = User::where('agency_id', $agencyId)
            ->whereNotNull('two_factor_confirmed_at')
            ->count();

        $apiKeyCount = ApiKey::query()->count();

        $activeApiKeyCount = ApiKey::query()
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();

        $lastReview = AccessReview::query()
            ->where('status', 'completed')
            ->latest('completed_at')
            ->first();

        $pendingReview = AccessReview::query()
            ->where('status', 'pending')
            ->first();

        return Inertia::render('settings/soc2-evidence', [
            'stats' => [
                'user_count' => $userCount,
                'two_factor_count' => $twoFactorCount,
                'two_factor_adoption_pct' => $userCount > 0 ? round($twoFactorCount / $userCount * 100) : 0,
                'api_key_count' => $apiKeyCount,
                'active_api_key_count' => $activeApiKeyCount,
            ],
            'lastReview' => $lastReview ? [
                'period' => $lastReview->period,
                'completed_at' => $lastReview->completed_at->toIso8601String(),
                'completed_by' => $lastReview->completedBy?->name,
            ] : null,
            'pendingReview' => $pendingReview ? [
                'id' => $pendingReview->id,
                'period' => $pendingReview->period,
                'due_at' => $pendingReview->due_at->toIso8601String(),
            ] : null,
        ]);
    }
}
