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
        Schema::table('scheduled_scans', function (Blueprint $table) {
            $table->string('timezone', 50)->nullable()->after('run_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scheduled_scans', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
