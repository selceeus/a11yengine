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
        Schema::table('governance_reports', function (Blueprint $table) {
            $table->string('legal_risk_rating', 20)->nullable()->after('compliance_status');
            $table->json('legal_precedents')->nullable()->after('legal_risk_rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('governance_reports', function (Blueprint $table) {
            $table->dropColumn(['legal_risk_rating', 'legal_precedents']);
        });
    }
};
