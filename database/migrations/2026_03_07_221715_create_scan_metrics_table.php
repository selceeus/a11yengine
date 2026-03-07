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
        Schema::create('scan_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_id')->constrained('scan_pages')->cascadeOnDelete();
            $table->string('metric_name');
            $table->decimal('metric_value', 12, 4);
            $table->string('metric_source');
            $table->timestamp('created_at')->nullable();

            $table->index(['agency_id', 'scan_id']);
            $table->index(['scan_id', 'page_id']);
            $table->index('metric_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scan_metrics');
    }
};
