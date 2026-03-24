<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('content_audits', function (Blueprint $table) {
            $table->json('reading_metrics')->nullable()->after('content_issues');
            $table->string('avg_reading_level', 100)->nullable()->after('reading_metrics');
            $table->unsignedInteger('avg_reading_time_seconds')->nullable()->after('avg_reading_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_audits', function (Blueprint $table): void {
            $table->dropColumn(['reading_metrics', 'avg_reading_level', 'avg_reading_time_seconds']);
        });
    }
};
