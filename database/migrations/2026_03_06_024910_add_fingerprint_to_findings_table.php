<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add as nullable first so the migration runs against existing rows.
        Schema::table('findings', function (Blueprint $table): void {
            $table->string('fingerprint')->nullable();
        });

        // Backfill fingerprints for existing rows.
        DB::table('findings')->orderBy('id')->chunk(500, function ($findings): void {
            foreach ($findings as $finding) {
                DB::table('findings')
                    ->where('id', $finding->id)
                    ->update([
                        'fingerprint' => sha1($finding->rule_key.'|'.($finding->element_identifier ?? '').'|'.$finding->page_url),
                    ]);
            }
        });

        // Now enforce: not-null + unique per scan.
        Schema::table('findings', function (Blueprint $table): void {
            $table->string('fingerprint')->nullable(false)->change();
            $table->unique(['scan_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table): void {
            $table->dropUnique(['scan_id', 'fingerprint']);
            $table->dropColumn('fingerprint');
        });
    }
};
