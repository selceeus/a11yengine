<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('findings', function (Blueprint $table): void {
            $table->json('tags')->nullable()->after('description');
            $table->string('help_url')->nullable()->after('tags');
            $table->text('element_html')->nullable()->after('element_identifier');
        });

        Schema::table('issues', function (Blueprint $table): void {
            $table->json('tags')->nullable()->after('description');
            $table->string('help_url')->nullable()->after('tags');
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
