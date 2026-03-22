<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('findings', function (Blueprint $table): void {
            $table->text('element_identifier')->nullable()->change();
            $table->text('page_url')->change();
            $table->text('help_url')->nullable()->change();
        });

        Schema::table('issues', function (Blueprint $table): void {
            $table->text('help_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table): void {
            $table->string('element_identifier')->nullable()->change();
            $table->string('page_url')->change();
            $table->string('help_url')->nullable()->change();
        });

        Schema::table('issues', function (Blueprint $table): void {
            $table->string('help_url')->nullable()->change();
        });
    }
};
