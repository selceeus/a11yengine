<?php

namespace Database\Factories;

use App\Models\AccessReview;
use App\Models\Agency;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccessReviewFactory extends Factory
{
    protected $model = AccessReview::class;

    public function definition(): array
    {
        $quarter = fake()->numberBetween(1, 4);
        $year = fake()->numberBetween(2024, 2026);

        return [
            'agency_id' => Agency::factory(),
            'period' => "{$year}-Q{$quarter}",
            'status' => 'pending',
            'due_at' => now()->addDays(30),
            'completed_at' => null,
            'completed_by' => null,
            'notes' => null,
        ];
    }
}
