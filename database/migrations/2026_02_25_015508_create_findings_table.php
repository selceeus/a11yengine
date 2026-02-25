<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('findings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('rule_key');
            $table->string('severity');
            $table->string('element_identifier')->nullable();
            $table->string('page_url');
            $table->text('message');
            $table->timestamp('detected_at');
            $table->timestamps();

            $table->index(['agency_id', 'severity']);
            $table->index(['agency_id', 'scan_id']);
            $table->index(['agency_id', 'property_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('findings');
    }
};
