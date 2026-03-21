<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table): void {
            $table->foreignId('assigned_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('resolution_notes')->nullable();

            $table->index('assigned_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table): void {
            $table->dropForeign(['assigned_user_id']);
            $table->dropIndex(['assigned_user_id']);
            $table->dropColumn(['assigned_user_id', 'resolution_notes']);
        });
    }
};
