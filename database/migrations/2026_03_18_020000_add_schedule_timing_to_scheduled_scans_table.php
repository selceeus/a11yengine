<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_scans', function (Blueprint $table): void {
            if (! Schema::hasColumn('scheduled_scans', 'run_time')) {
                $table->string('run_time')->nullable()->after('frequency');
            }
            if (! Schema::hasColumn('scheduled_scans', 'run_day_of_week')) {
                $table->tinyInteger('run_day_of_week')->nullable()->after('run_time');
            }
            if (! Schema::hasColumn('scheduled_scans', 'run_day_of_month')) {
                $table->tinyInteger('run_day_of_month')->nullable()->after('run_day_of_week');
            }
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_scans', function (Blueprint $table): void {
            $table->dropColumn(['run_time', 'run_day_of_week', 'run_day_of_month']);
        });
    }
};
