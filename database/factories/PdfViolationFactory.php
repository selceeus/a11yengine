<?php

namespace Database\Factories;

use App\Enums\FindingSeverity;
use App\Models\PdfDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

class PdfViolationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pdf_document_id' => PdfDocument::factory(),
            'rule_key' => fake()->randomElement(['pdf/untagged', 'pdf/no-title', 'pdf/no-language', 'pdf/image-only', 'pdf/no-bookmarks', 'pdf/figure-no-alt']),
            'severity' => fake()->randomElement(FindingSeverity::cases())->value,
            'wcag_criteria' => fake()->randomElement(['1.1.1', '1.3.1', '2.4.2', '2.4.5', '3.1.1', null]),
            'description' => fake()->sentence(),
            'element_context' => null,
            'page_number' => null,
        ];
    }
}
