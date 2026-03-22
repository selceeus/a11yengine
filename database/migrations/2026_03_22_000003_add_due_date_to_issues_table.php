<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table): void {
            $table->date('due_date')->nullable()->after('resolved_at');
        });

        // Backfill due dates for existing issues based on severity and first_detected_at.
        $severityDays = [
            'critical' => 7,
            'high' => 14,
            'medium' => 30,
            'low' => 60,
        ];

        foreach ($severityDays as $severity => $days) {
            DB::table('issues')
                ->whereNull('due_date')
                ->where('severity', $severity)
                ->whereNotNull('first_detected_at')
                ->lazyById()
                ->each(function (object $issue) use ($days): void {
                    DB::table('issues')
                        ->where('id', $issue->id)
                        ->update(['due_date' => \Carbon\Carbon::parse($issue->first_detected_at)->addDays($days)->toDateString()]);
                });
        }
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table): void {
            $table->dropColumn('due_date');
        });
    }
};
