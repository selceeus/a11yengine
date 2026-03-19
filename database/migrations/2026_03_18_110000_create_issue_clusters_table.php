<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_clusters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->json('clusters')->nullable();
            $table->integer('total_clusters')->nullable();
            $table->integer('open_issues_analyzed')->nullable();
            $table->text('prompt_context')->nullable();
            $table->longText('raw_ai_response')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'property_id']);
            $table->index(['property_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_clusters');
    }
};
