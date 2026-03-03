<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scans', function (Blueprint $table): void {
            $table->unsignedInteger('pages_scanned')->nullable()->after('status');
            $table->unsignedInteger('total_violations')->nullable()->after('pages_scanned');
            $table->string('raw_output_path')->nullable()->after('total_violations');
        });
    }

    public function down(): void
    {
        Schema::table('scans', function (Blueprint $table): void {
            $table->dropColumn(['pages_scanned', 'total_violations', 'raw_output_path']);
        });
    }
};
