<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agency_invitations', function (Blueprint $table): void {
            $table->string('token_hash', 64)->nullable()->unique()->after('token');
        });

        // Backfill: hash any existing plain-text tokens.
        DB::table('agency_invitations')
            ->whereNotNull('token')
            ->lazyById()
            ->each(function (object $row): void {
                DB::table('agency_invitations')
                    ->where('id', $row->id)
                    ->update(['token_hash' => hash('sha256', $row->token)]);
            });

        Schema::table('agency_invitations', function (Blueprint $table): void {
            $table->dropColumn('token');
        });
    }

    public function down(): void
    {
        Schema::table('agency_invitations', function (Blueprint $table): void {
            $table->string('token', 64)->nullable()->unique()->after('email');
            $table->dropColumn('token_hash');
        });
    }
};
