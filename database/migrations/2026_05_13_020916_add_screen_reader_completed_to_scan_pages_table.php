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
            $table->boolean('screen_reader_completed')->nullable()->default(null)->after('lighthouse_completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scan_pages', function (Blueprint $table) {
            $table->dropColumn('screen_reader_completed');
        });
    }
};
