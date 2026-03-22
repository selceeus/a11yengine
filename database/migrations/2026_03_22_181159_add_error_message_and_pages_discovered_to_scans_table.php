<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scans', function (Blueprint $table): void {
            $table->unsignedInteger('pages_discovered')->nullable()->after('pages_scanned');
            $table->text('error_message')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('scans', function (Blueprint $table): void {
            $table->dropColumn(['pages_discovered', 'error_message']);
        });
    }
};
