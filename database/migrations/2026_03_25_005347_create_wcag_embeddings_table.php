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
        Schema::create('wcag_embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('criterion', 20)->index();
            $table->string('level', 3);
            $table->string('title');
            $table->text('chunk');
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
        Schema::dropIfExists('wcag_embeddings');
    }
};
