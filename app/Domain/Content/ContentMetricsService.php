<?php

namespace App\Domain\Content;

class ContentMetricsService
{
    /**
     * Average adult reading speed in words per minute.
     */
    private const WORDS_PER_MINUTE = 230;

    /**
     * Compute Flesch-Kincaid reading metrics for the given plain-text content.
     *
     * @return array{
     *     page_url: string,
     *     reading_level: string,
     *     reading_time: string,
     *     reading_time_seconds: int,
     *     word_count: int,
     *     flesch_score: float|null
     * }
     */
    public function computeMetrics(string $text, string $url): array
    {
        $text = trim($text);

        if ($text === '') {
            return $this->emptyResult($url);
        }

        $words = $this->countWords($text);
        $sentences = $this->countSentences($text);
        $syllables = $this->countSyllables($text);

        if ($sentences === 0 || $words === 0) {
            return $this->emptyResult($url);
        }

        $avgWordsPerSentence = $words / $sentences;
        $avgSyllablesPerWord = $syllables / $words;

        // Flesch Reading Ease (higher = easier, 0–100 scale)
        $fleschScore = round(
            206.835 - (1.015 * $avgWordsPerSentence) - (84.6 * $avgSyllablesPerWord),
            1,
        );
        $fleschScore = (float) max(0.0, min(100.0, $fleschScore));

        // Flesch-Kincaid Grade Level
        $fkGrade = (0.39 * $avgWordsPerSentence) + (11.8 * $avgSyllablesPerWord) - 15.59;
        $fkGrade = (int) round(max(1.0, $fkGrade));

        $readingTimeSeconds = (int) max(1, round(($words / self::WORDS_PER_MINUTE) * 60));

        return [
            'page_url' => $url,
            'reading_level' => "Grade {$fkGrade} (Flesch-Kincaid)",
            'reading_time' => $this->formatReadingTime($readingTimeSeconds),
            'reading_time_seconds' => $readingTimeSeconds,
            'word_count' => $words,
            'flesch_score' => $fleschScore,
        ];
    }

    /**
     * Count words in the text using simple whitespace splitting.
     */
    private function countWords(string $text): int
    {
        $wordCount = preg_match_all('/\S+/', $text, $matches);

        return $wordCount !== false ? $wordCount : 0;
    }

    /**
     * Count sentences by splitting on terminal punctuation.
     * Falls back to treating the entire text as one sentence.
     */
    private function countSentences(string $text): int
    {
        $count = preg_match_all('/[.!?]+(?:\s|$)/u', $text, $matches);

        return ($count !== false && $count > 0) ? $count : 1;
    }

    /**
     * Count total syllables across all words in the text.
     * Uses an approximation based on vowel clusters.
     */
    private function countSyllables(string $text): int
    {
        preg_match_all('/\S+/', mb_strtolower($text), $wordMatches);
        $total = 0;

        foreach ($wordMatches[0] as $word) {
            $total += $this->syllablesInWord($word);
        }

        return max($total, 1);
    }

    /**
     * Approximate syllable count for a single word.
     *
     * Algorithm:
     *  1. Strip non-alphabetic characters.
     *  2. Count contiguous vowel groups (each group ≈ one syllable).
     *  3. Subtract one syllable for silent 'e' at end of word.
     *  4. Clamp to minimum of 1.
     */
    private function syllablesInWord(string $word): int
    {
        // Strip non-alpha
        $word = preg_replace('/[^a-z]/', '', $word) ?? '';

        if (strlen($word) <= 3) {
            return 1;
        }

        // Count vowel groups
        $count = preg_match_all('/[aeiou]+/', $word, $matches);
        $syllables = $count !== false ? $count : 1;

        // Subtract silent trailing 'e' (e.g. "made", "have", "scene")
        if (substr($word, -1) === 'e' && substr($word, -2, 1) !== 'e') {
            $syllables = max(1, $syllables - 1);
        }

        return max(1, $syllables);
    }

    /**
     * Format a duration in seconds as a human-readable string.
     */
    public function formatReadingTime(int $seconds): string
    {
        $mins = intdiv($seconds, 60);
        $secs = $seconds % 60;

        if ($mins > 0 && $secs > 0) {
            return "{$mins} min {$secs} sec";
        }

        if ($mins > 0) {
            return "{$mins} min";
        }

        return "{$secs} sec";
    }

    /**
     * Return a zeroed result for pages with no usable text.
     *
     * @return array{page_url: string, reading_level: string, reading_time: string, reading_time_seconds: int, word_count: int, flesch_score: null}
     */
    private function emptyResult(string $url): array
    {
        return [
            'page_url' => $url,
            'reading_level' => 'Grade 1 (Flesch-Kincaid)',
            'reading_time' => '1 sec',
            'reading_time_seconds' => 1,
            'word_count' => 0,
            'flesch_score' => null,
        ];
    }
}
