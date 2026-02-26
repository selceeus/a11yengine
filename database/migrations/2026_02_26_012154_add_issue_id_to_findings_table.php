<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('findings', function (Blueprint $table): void {
            $table->foreignId('issue_id')->nullable()->constrained()->nullOnDelete()->after('agency_id');
        });
    }

    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table): void {
            $table->dropForeignIdFor(\App\Models\Issue::class);
            $table->dropColumn('issue_id');
        });
    }
};
