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
            $table->boolean('content_completed')->nullable()->default(null)->after('screen_reader_completed');
            $table->text('visible_text')->nullable()->after('content_completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scan_pages', function (Blueprint $table) {
            $table->dropColumn(['content_completed', 'visible_text']);
        });
    }
};
