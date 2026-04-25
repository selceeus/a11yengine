<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('period', 10)->comment('e.g. 2026-Q2');
            $table->string('status', 10)->default('pending')->comment('pending|completed');
            $table->timestamp('due_at');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'status']);
            $table->unique(['agency_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_reviews');
    }
};
