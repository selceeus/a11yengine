<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LawsuitEmbedding>
 */
class LawsuitEmbeddingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'case_name' => fake()->company().' v. '.fake()->company(),
            'filed_year' => fake()->numberBetween(2010, 2024),
            'industry' => fake()->randomElement(['retail', 'finance', 'healthcare', 'education', 'technology']),
            'violation_type' => fake()->randomElement(['screen reader incompatibility', 'keyboard navigation failure', 'color contrast failure']),
            'wcag_criteria' => ['1.1.1', '4.1.2'],
            'outcome' => fake()->randomElement(['plaintiff_won', 'settled', 'dismissed']),
            'settlement_amount' => null,
            'summary' => fake()->paragraph(),
            'embedding' => array_fill(0, 1536, 0.0),
            'metadata' => ['source' => 'lawsuits.json'],
        ];
    }
}
