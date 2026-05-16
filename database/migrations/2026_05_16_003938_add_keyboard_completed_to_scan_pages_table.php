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
            $table->boolean('keyboard_completed')->nullable()->default(null)->after('content_completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scan_pages', function (Blueprint $table) {
            $table->dropColumn('keyboard_completed');
        });
    }
};
