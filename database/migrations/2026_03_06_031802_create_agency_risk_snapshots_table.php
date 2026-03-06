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
        Schema::create('agency_risk_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->integer('risk_score');
            $table->integer('open_issue_count');
            $table->date('snapshot_date');
            $table->timestamp('created_at')->useCurrent();

            $table->index('agency_id');
            $table->index('snapshot_date');
            $table->index(['agency_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_risk_snapshots');
    }
};
