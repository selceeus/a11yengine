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
        Schema::create('lighthouse_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete()->index();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->unsignedTinyInteger('performance_score')->nullable();
            $table->unsignedTinyInteger('accessibility_score')->nullable();
            $table->unsignedTinyInteger('best_practices_score')->nullable();
            $table->unsignedTinyInteger('seo_score')->nullable();
            $table->decimal('first_contentful_paint', 8, 2)->nullable();
            $table->decimal('largest_contentful_paint', 8, 2)->nullable();
            $table->decimal('total_blocking_time', 8, 2)->nullable();
            $table->decimal('cumulative_layout_shift', 6, 4)->nullable();
            $table->json('raw_metrics')->nullable();
            $table->timestamps();
            $table->index(['agency_id', 'scan_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lighthouse_results');
    }
};
