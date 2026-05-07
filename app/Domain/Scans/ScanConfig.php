<?php

namespace App\Domain\Scans;

class ScanConfig
{
    public function __construct(
        public readonly int $maxPages = 50,
        public readonly int $maxDepth = 5,
        /** @var string[] */
        public readonly array $includePatterns = [],
        /** @var string[] */
        public readonly array $excludePatterns = [],
        public readonly string $wcagVersion = 'wcag21',
        /** @var string[] */
        public readonly array $orderedUrls = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            maxPages: isset($data['max_pages']) ? (int) $data['max_pages'] : 50,
            maxDepth: isset($data['max_depth']) ? (int) $data['max_depth'] : 5,
            includePatterns: isset($data['include_patterns']) && is_array($data['include_patterns'])
                ? array_values(array_filter($data['include_patterns'], 'is_string'))
                : [],
            excludePatterns: isset($data['exclude_patterns']) && is_array($data['exclude_patterns'])
                ? array_values(array_filter($data['exclude_patterns'], 'is_string'))
                : [],
            wcagVersion: in_array($data['wcag_version'] ?? 'wcag21', ['wcag21', 'wcag22'], true)
                ? ($data['wcag_version'] ?? 'wcag21')
                : 'wcag21',
            orderedUrls: isset($data['ordered_urls']) && is_array($data['ordered_urls'])
                ? array_values(array_filter($data['ordered_urls'], 'is_string'))
                : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'max_pages' => $this->maxPages,
            'max_depth' => $this->maxDepth,
            'include_patterns' => $this->includePatterns,
            'exclude_patterns' => $this->excludePatterns,
            'wcag_version' => $this->wcagVersion,
            'ordered_urls' => $this->orderedUrls,
        ];
    }

    public function merge(self $override): self
    {
        return new self(
            maxPages: $override->maxPages !== 50 ? $override->maxPages : $this->maxPages,
            maxDepth: $override->maxDepth !== 5 ? $override->maxDepth : $this->maxDepth,
            includePatterns: $override->includePatterns !== [] ? $override->includePatterns : $this->includePatterns,
            excludePatterns: $override->excludePatterns !== [] ? $override->excludePatterns : $this->excludePatterns,
            wcagVersion: $override->wcagVersion !== 'wcag21' ? $override->wcagVersion : $this->wcagVersion,
            orderedUrls: $override->orderedUrls !== [] ? $override->orderedUrls : $this->orderedUrls,
        );
    }

    /**
     * Build the axe runOnly tag-set for the configured WCAG version.
     *
     * @return string[]
     */
    public function axeTags(): array
    {
        $base = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'best-practice'];

        if ($this->wcagVersion === 'wcag22') {
            $base[] = 'wcag22a';
            $base[] = 'wcag22aa';
        }

        return $base;
    }
}
