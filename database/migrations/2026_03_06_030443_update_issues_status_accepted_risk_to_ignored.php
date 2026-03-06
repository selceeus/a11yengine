<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('issues')
            ->where('status', 'accepted_risk')
            ->update(['status' => 'ignored']);
    }

    public function down(): void
    {
        DB::table('issues')
            ->where('status', 'ignored')
            ->update(['status' => 'accepted_risk']);
    }
};
