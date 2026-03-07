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
        Schema::table('scan_pages', function (Blueprint $table) {
            $table->boolean('axe_completed')->default(false)->after('status');
            $table->boolean('lighthouse_completed')->nullable()->default(null)->after('axe_completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scan_pages', function (Blueprint $table) {
            $table->dropColumn(['axe_completed', 'lighthouse_completed']);
        });
    }
};
