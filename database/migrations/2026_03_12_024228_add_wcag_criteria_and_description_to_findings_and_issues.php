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
        Schema::table('findings', function (Blueprint $table): void {
            $table->string('wcag_criteria')->nullable()->after('wcag_category');
            $table->text('description')->nullable()->after('wcag_criteria');
        });

        Schema::table('issues', function (Blueprint $table): void {
            $table->string('wcag_criteria')->nullable()->after('wcag_category');
            $table->text('description')->nullable()->after('wcag_criteria');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table): void {
            $table->dropColumn(['wcag_criteria', 'description']);
        });

        Schema::table('issues', function (Blueprint $table): void {
            $table->dropColumn(['wcag_criteria', 'description']);
        });
    }
};
