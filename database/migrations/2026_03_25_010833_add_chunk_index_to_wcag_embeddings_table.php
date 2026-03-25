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
        Schema::table('wcag_embeddings', function (Blueprint $table) {
            $table->unsignedSmallInteger('chunk_index')->default(0)->after('criterion');
            $table->unique(['criterion', 'chunk_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wcag_embeddings', function (Blueprint $table) {
            $table->dropUnique(['criterion', 'chunk_index']);
            $table->dropColumn('chunk_index');
        });
    }
};
