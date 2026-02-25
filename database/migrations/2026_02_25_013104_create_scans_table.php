<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('raw_summary')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'status']);
            $table->index(['agency_id', 'property_id']);
            $table->index(['agency_id', 'organization_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scans');
    }
};
