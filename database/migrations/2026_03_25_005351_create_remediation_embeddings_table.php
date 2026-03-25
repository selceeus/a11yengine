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
        Schema::create('remediation_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->nullable()->constrained('issues')->nullOnDelete();
            $table->string('rule_key', 100)->index();
            $table->string('wcag_criteria', 20)->nullable();
            $table->text('description');
            $table->text('resolution');
            $table->string('outcome', 20)->nullable();
            // Stored as a JSON float array; swap to vector(1536) when pgvector is available
            $table->jsonb('embedding');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('remediation_embeddings');
    }
};
