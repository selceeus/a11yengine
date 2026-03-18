<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table): void {
            $table->string('ai_remediation_status')->nullable()->after('help_url');
            $table->json('ai_suggestions')->nullable()->after('ai_remediation_status');
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table): void {
            $table->dropColumn(['ai_remediation_status', 'ai_suggestions']);
        });
    }
};
