<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AccessReview;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccessReviewsExportController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        $filename = 'access-reviews-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'period',
                'status',
                'due_at',
                'completed_at',
                'completed_by',
                'notes',
            ]);

            AccessReview::query()
                ->with('completedBy:id,name')
                ->orderByDesc('created_at')
                ->chunk(200, function ($reviews) use ($handle): void {
                    foreach ($reviews as $review) {
                        fputcsv($handle, [
                            $review->period,
                            $review->status->value,
                            $review->due_at->toIso8601String(),
                            $review->completed_at?->toIso8601String(),
                            $review->completedBy?->name,
                            $review->notes,
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
