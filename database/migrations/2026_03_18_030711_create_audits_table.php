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
        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('status')->default('pending');
            $table->json('source_scan_ids')->nullable();
            $table->text('prompt_context')->nullable();
            $table->longText('raw_ai_response')->nullable();
            $table->text('executive_summary')->nullable();
            $table->json('compliance_status')->nullable();
            $table->json('top_risks')->nullable();
            $table->json('issue_details')->nullable();
            $table->json('remediations')->nullable();
            $table->json('summary_statistics')->nullable();
            $table->integer('overall_score')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index('agency_id');
            $table->index(['property_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};
