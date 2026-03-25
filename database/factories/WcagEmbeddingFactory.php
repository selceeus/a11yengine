<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WcagEmbedding>
 */
class WcagEmbeddingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'criterion' => fake()->randomElement(['1.1.1', '1.3.1', '1.4.3', '2.1.1', '4.1.2']),
            'chunk_index' => fake()->numberBetween(0, 3),
            'level' => fake()->randomElement(['A', 'AA']),
            'title' => fake()->sentence(3),
            'chunk' => fake()->paragraph(),
            'embedding' => array_fill(0, 1536, 0.0),
            'metadata' => ['source' => 'wcag_criteria.json'],
        ];
    }
}
