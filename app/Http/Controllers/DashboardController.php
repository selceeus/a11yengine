<?php

namespace App\Http\Controllers;

use App\Enums\ScanStatus;
use App\Models\Scan;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('dashboard', [
            'defaultPropertyId' => Scan::query()
                ->where('status', ScanStatus::Completed)
                ->orderByDesc('completed_at')
                ->value('property_id'),
        ]);
    }
}
