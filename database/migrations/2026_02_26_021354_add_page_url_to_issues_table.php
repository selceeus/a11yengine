<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table): void {
            $table->string('page_url')->after('rule_key');
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table): void {
            $table->dropColumn('page_url');
        });
    }
};
