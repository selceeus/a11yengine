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
        Schema::create('governance_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->nullOnDelete()->constrained();
            $table->foreignId('property_id')->nullable()->nullOnDelete()->constrained();
            $table->string('report_scope')->default('property'); // property | agency
            $table->date('period_from');
            $table->date('period_to');
            $table->string('status')->default('pending');
            $table->text('executive_narrative')->nullable();
            $table->json('risk_trend')->nullable();
            $table->json('severity_breakdown')->nullable();
            $table->json('remediation_progress')->nullable();
            $table->json('compliance_status')->nullable();
            $table->json('recommendations')->nullable();
            $table->json('summary_cards')->nullable();
            $table->text('prompt_context')->nullable();
            $table->longText('raw_ai_response')->nullable();
            $table->string('error_message', 250)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->boolean('is_scheduled')->default(false);
            $table->timestamps();

            $table->index(['agency_id', 'property_id']);
            $table->index(['agency_id', 'report_scope', 'period_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('governance_reports');
    }
};
