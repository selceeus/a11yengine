<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->string('slug')->nullable()->unique()->after('name');
        });

        DB::table('properties')->get()->each(function (object $property): void {
            DB::table('properties')
                ->where('id', $property->id)
                ->update(['slug' => Str::slug($property->name).'-'.$property->id]);
        });

        Schema::table('properties', function (Blueprint $table): void {
            $table->string('slug')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->dropColumn('slug');
        });
    }
};
