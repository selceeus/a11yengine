<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('base_url');
            $table->string('status');
            $table->timestamps();

            $table->index(['agency_id', 'status']);
            $table->index(['agency_id', 'organization_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
