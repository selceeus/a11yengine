<?php

namespace Database\Factories;

use App\Enums\ContentAuditStatus;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContentAuditFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'organization_id' => Organization::factory()->state(fn (array $attributes) => [
                'agency_id' => $attributes['agency_id'],
            ]),
            'property_id' => Property::factory()->state(fn (array $attributes) => [
                'agency_id' => $attributes['agency_id'],
                'organization_id' => $attributes['organization_id'],
            ]),
            'status' => ContentAuditStatus::Pending,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ContentAuditStatus::Completed,
            'total_issues' => 3,
            'pages_analyzed' => 12,
            'generated_at' => now(),
            'content_issues' => [
                [
                    'page_url' => '/about',
                    'issue_id' => null,
                    'rule_key' => 'link-name',
                    'category' => 'link_text',
                    'issue_type' => 'Ambiguous link text',
                    'element_html' => '<a href="/read-more">Read more</a>',
                    'current_text' => 'Read more',
                    'issue' => 'Link text "Read more" is ambiguous and does not describe the destination.',
                    'suggestion' => 'Use descriptive text such as "Read more about our accessibility policy".',
                    'severity' => 'serious',
                    'wcag_criteria' => '2.4.4',
                    'writer_note' => 'Rewrite the link text to describe where the link goes.',
                    'developer_note' => 'Consider adding aria-label if the surrounding context cannot be changed.',
                ],
                [
                    'page_url' => '/products',
                    'issue_id' => null,
                    'rule_key' => 'image-alt',
                    'category' => 'alt_text',
                    'issue_type' => 'Missing alt text',
                    'element_html' => '<img src="/images/hero.jpg">',
                    'current_text' => null,
                    'issue' => 'Hero image is missing an alt attribute entirely.',
                    'suggestion' => 'Add a descriptive alt attribute conveying the image meaning.',
                    'severity' => 'critical',
                    'wcag_criteria' => '1.1.1',
                    'writer_note' => 'Describe the image content in 10 words or fewer.',
                    'developer_note' => 'Add alt="" only if the image is purely decorative.',
                ],
                [
                    'page_url' => '/contact',
                    'issue_id' => null,
                    'rule_key' => 'label',
                    'category' => 'form_label',
                    'issue_type' => 'Missing form label',
                    'element_html' => '<input type="email" placeholder="Email">',
                    'current_text' => null,
                    'issue' => 'Email input uses only a placeholder, which is not a sufficient label for assistive technologies.',
                    'suggestion' => 'Add an associated <label> element or aria-label attribute.',
                    'severity' => 'critical',
                    'wcag_criteria' => '1.3.1',
                    'writer_note' => 'Write a short visible label such as "Email address".',
                    'developer_note' => 'Associate the label via the `for` attribute or wrap the input in the label element.',
                ],
            ],
            'reading_metrics' => [
                [
                    'page_url' => '/about',
                    'reading_level' => 'Grade 10 (Flesch-Kincaid)',
                    'reading_time' => '3 min',
                    'reading_time_seconds' => 180,
                    'word_count' => 690,
                    'flesch_score' => 52.3,
                ],
                [
                    'page_url' => '/products',
                    'reading_level' => 'Grade 8 (Flesch-Kincaid)',
                    'reading_time' => '1 min 30 sec',
                    'reading_time_seconds' => 90,
                    'word_count' => 345,
                    'flesch_score' => 63.1,
                ],
            ],
            'avg_reading_level' => 'Grade 9 (Flesch-Kincaid)',
            'avg_reading_time_seconds' => 135,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ContentAuditStatus::Failed,
            'error_message' => 'AI provider returned an unexpected response.',
        ]);
    }
}
