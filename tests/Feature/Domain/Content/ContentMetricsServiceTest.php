<?php

use App\Domain\Content\ContentMetricsService;

beforeEach(function (): void {
    $this->service = app(ContentMetricsService::class);
});

// ─── Empty / blank text ───────────────────────────────────────────────────────

it('returns a zeroed result for empty text', function (): void {
    $result = $this->service->computeMetrics('', 'https://example.com/');

    expect($result['word_count'])->toBe(0)
        ->and($result['flesch_score'])->toBeNull()
        ->and($result['page_url'])->toBe('https://example.com/');
});

it('returns a zeroed result for whitespace-only text', function (): void {
    $result = $this->service->computeMetrics('   ', 'https://example.com/');

    expect($result['word_count'])->toBe(0);
});

// ─── Return shape ─────────────────────────────────────────────────────────────

it('returns all required keys', function (): void {
    $result = $this->service->computeMetrics('The quick brown fox jumps over the lazy dog.', 'https://example.com/');

    expect($result)->toHaveKeys([
        'page_url', 'reading_level', 'reading_time', 'reading_time_seconds', 'word_count', 'flesch_score',
    ]);
});

it('returns the correct page_url', function (): void {
    $result = $this->service->computeMetrics('Some text here.', 'https://example.com/about');

    expect($result['page_url'])->toBe('https://example.com/about');
});

// ─── Word count ───────────────────────────────────────────────────────────────

it('counts words correctly', function (): void {
    $result = $this->service->computeMetrics('one two three four five', 'https://example.com/');

    expect($result['word_count'])->toBe(5);
});

// ─── Reading time ─────────────────────────────────────────────────────────────

it('reading_time_seconds is at least 1', function (): void {
    $result = $this->service->computeMetrics('Hello.', 'https://example.com/');

    expect($result['reading_time_seconds'])->toBeGreaterThanOrEqual(1);
});

it('formats reading time as "N min" for text over a minute', function (): void {
    // ~230 words = 1 minute reading time
    $text = implode(' ', array_fill(0, 230, 'word')).'.';
    $result = $this->service->computeMetrics($text, 'https://example.com/');

    expect($result['reading_time'])->toContain('min');
});

it('formats reading time as seconds for very short text', function (): void {
    $result = $this->service->computeMetrics('Hello world.', 'https://example.com/');

    expect($result['reading_time'])->toContain('sec');
});

// ─── Flesch score ─────────────────────────────────────────────────────────────

it('returns a flesch_score between 0 and 100', function (): void {
    $text = 'The cat sat on the mat. It was a fat cat. The cat had a hat.';
    $result = $this->service->computeMetrics($text, 'https://example.com/');

    expect($result['flesch_score'])
        ->toBeGreaterThanOrEqual(0.0)
        ->toBeLessThanOrEqual(100.0);
});

it('assigns a higher flesch score to simpler text than complex text', function (): void {
    $simple = 'The cat sat. The dog ran. The bird flew. It was fun. Go now.';
    $complex = 'The multifaceted interconnectedness of interdisciplinary epistemological frameworks necessitates substantially comprehensive reconceptualisation.';

    $simpleScore = $this->service->computeMetrics($simple, 'https://example.com/')['flesch_score'];
    $complexScore = $this->service->computeMetrics($complex, 'https://example.com/')['flesch_score'];

    expect($simpleScore)->toBeGreaterThan($complexScore);
});

// ─── Reading level ────────────────────────────────────────────────────────────

it('reading_level string contains Flesch-Kincaid label', function (): void {
    $text = 'The quick brown fox jumps over the lazy dog. Pack my box with five dozen liquor jugs.';
    $result = $this->service->computeMetrics($text, 'https://example.com/');

    expect($result['reading_level'])->toContain('Flesch-Kincaid');
});

it('reading_level contains a Grade N pattern', function (): void {
    $text = 'The quick brown fox jumps over the lazy dog. Pack my box with five dozen liquor jugs.';
    $result = $this->service->computeMetrics($text, 'https://example.com/');

    expect($result['reading_level'])->toMatch('/Grade \d+/');
});

// ─── formatReadingTime helper ────────────────────────────────────────────────

it('formats 90 seconds as "1 min 30 sec"', function (): void {
    expect($this->service->formatReadingTime(90))->toBe('1 min 30 sec');
});

it('formats 60 seconds as "1 min"', function (): void {
    expect($this->service->formatReadingTime(60))->toBe('1 min');
});

it('formats 45 seconds as "45 sec"', function (): void {
    expect($this->service->formatReadingTime(45))->toBe('45 sec');
});
