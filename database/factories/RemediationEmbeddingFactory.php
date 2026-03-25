<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RemediationEmbedding>
 */
class RemediationEmbeddingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'issue_id' => null,
            'rule_key' => 'image-alt',
            'wcag_criteria' => '1.1.1',
            'description' => fake()->sentence(),
            'resolution' => fake()->paragraph(),
            'outcome' => 'high',
            'embedding' => array_fill(0, 1536, 0.0),
            'metadata' => ['indexed_at' => now()->toIso8601String()],
        ];
    }
}
