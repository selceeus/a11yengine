<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('rule_key');
            $table->string('severity');
            $table->string('status')->default('open');
            $table->integer('occurrence_count')->default(1);
            $table->integer('risk_weight')->default(0);
            $table->timestamp('first_detected_at');
            $table->timestamp('last_detected_at');
            $table->timestamps();

            $table->index('agency_id');
            $table->index('organization_id');
            $table->index('property_id');
            $table->index('rule_key');
            $table->index('status');
            $table->index(['agency_id', 'rule_key', 'property_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
