<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $agency = \App\Models\Agency::factory()->create([
            'name' => 'Demo Agency',
            'slug' => 'demo-agency',
            'billing_email' => 'admin@demo.test',
        ]);

        User::factory()->create([
            'agency_id' => $agency->id,
            'name' => 'Admin User',
            'email' => 'admin@demo.test',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);
    }
}
