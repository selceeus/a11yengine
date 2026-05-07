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
        Schema::table('scans', function (Blueprint $table): void {
            $table->foreignId('scan_journey_id')
                ->nullable()
                ->after('target_url')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scans', function (Blueprint $table): void {
            $table->dropForeign(['scan_journey_id']);
            $table->dropColumn('scan_journey_id');
        });
    }
};
