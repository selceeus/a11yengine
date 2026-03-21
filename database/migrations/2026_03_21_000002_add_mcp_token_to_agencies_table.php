<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table): void {
            $table->string('mcp_token')->nullable()->unique()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table): void {
            $table->dropColumn('mcp_token');
        });
    }
};
