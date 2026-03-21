<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scans', function (Blueprint $table): void {
            $table->unsignedInteger('pages_scanned')->nullable();
            $table->unsignedInteger('total_violations')->nullable();
            $table->string('raw_output_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('scans', function (Blueprint $table): void {
            $table->dropColumn(['pages_scanned', 'total_violations', 'raw_output_path']);
        });
    }
};
