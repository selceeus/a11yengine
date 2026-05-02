<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table): void {
            $table->string('mcp_token_hash', 64)->nullable()->unique()->after('mcp_token');
        });

        // Backfill: hash any existing plain-text tokens.
        DB::table('agencies')
            ->whereNotNull('mcp_token')
            ->lazyById()
            ->each(function (object $agency): void {
                DB::table('agencies')
                    ->where('id', $agency->id)
                    ->update(['mcp_token_hash' => hash('sha256', $agency->mcp_token)]);
            });

        Schema::table('agencies', function (Blueprint $table): void {
            $table->dropColumn('mcp_token');
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table): void {
            $table->string('mcp_token')->nullable()->unique()->after('slug');
            $table->dropColumn('mcp_token_hash');
        });
    }
};
