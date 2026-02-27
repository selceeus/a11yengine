<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Finding;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $agency = Agency::factory()->create([
            'name' => 'Demo Agency',
        ]);

        $organization = Organization::factory()->create([
            'agency_id' => $agency->id,
            'name' => 'Demo Organization',
        ]);

        $property = Property::factory()->create([
            'agency_id' => $agency->id,
            'organization_id' => $organization->id,
            'name' => 'Demo Property',
            'base_url' => 'https://demo.example.com',
        ]);

        $scan = Scan::factory()->create([
            'agency_id' => $agency->id,
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'status' => 'completed',
            'started_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(2),
        ]);

        Finding::factory(15)->create([
            'agency_id' => $agency->id,
            'scan_id' => $scan->id,
            'property_id' => $property->id,
            'detected_at' => now(),
        ]);

        $this->command->table(
            ['Key', 'Value'],
            [
                ['Agency ID', $agency->id],
                ['Organization ID', $organization->id],
                ['Property ID', $property->id],
                ['Scan ID', $scan->id],
                ['Findings seeded', 15],
            ]
        );

        $this->command->info('API endpoint ready:');
        $this->command->line("  POST /api/organizations/{$organization->id}/risk-snapshot");
    }
}
