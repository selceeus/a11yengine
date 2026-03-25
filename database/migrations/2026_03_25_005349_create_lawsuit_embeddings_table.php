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
        Schema::create('lawsuit_embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('case_name');
            $table->unsignedSmallInteger('filed_year');
            $table->string('industry', 50)->nullable()->index();
            $table->string('violation_type');
            $table->jsonb('wcag_criteria')->nullable();
            $table->string('outcome', 50);
            $table->unsignedInteger('settlement_amount')->nullable();
            $table->text('summary');
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
        Schema::dropIfExists('lawsuit_embeddings');
    }
};
