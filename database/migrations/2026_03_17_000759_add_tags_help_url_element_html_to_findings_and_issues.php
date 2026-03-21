<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('findings', function (Blueprint $table): void {
            $table->json('tags')->nullable();
            $table->string('help_url')->nullable();
            $table->text('element_html')->nullable();
        });

        Schema::table('issues', function (Blueprint $table): void {
            $table->json('tags')->nullable();
            $table->string('help_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table): void {
            $table->dropColumn(['tags', 'help_url', 'element_html']);
        });

        Schema::table('issues', function (Blueprint $table): void {
            $table->dropColumn(['tags', 'help_url']);
        });
    }
};
