<?php

declare(strict_types=1);

namespace Opik\Evaluation\Metrics;

/**
 * LevenshteinRatio metric - calculates text similarity based on Levenshtein distance.
 *
 * Returns a ratio between 0.0 (completely different) and 1.0 (identical).
 * The ratio is calculated as: 1 - (levenshtein_distance / max_length)
 */
final class LevenshteinRatio extends BaseMetric
{
    public function __construct(string $name = 'levenshtein_ratio')
    {
        parent::__construct($name);
    }

    /**
     * Calculates a similarity score based on Levenshtein distance.
     *
     * @param array<string, mixed> $input Must contain 'output' and 'expected' keys
     *
     * @return ScoreResult Score result (0.0 to 1.0)
     */
    public function score(array $input): ScoreResult
    {
        $output = (string) ($input['output'] ?? '');
        $expected = (string) ($input['expected'] ?? '');

        // Handle empty strings
        if ($output === '' && $expected === '') {
            return new ScoreResult(
                name: $this->name,
                value: 1.0,
                reason: 'Both strings are empty - perfect match',
            );
        }

        $maxLength = max(\strlen($output), \strlen($expected));

        if ($maxLength === 0) {
            return new ScoreResult(
                name: $this->name,
                value: 1.0,
                reason: 'Both strings are empty - perfect match',
            );
        }

        $distance = levenshtein($output, $expected);

        // levenshtein() returns -1 if either string is longer than 255 characters
        if ($distance === -1) {
            // Fall back to similar_text for long strings
            similar_text($output, $expected, $percent);
            $ratio = $percent / 100.0;

            return new ScoreResult(
                name: $this->name,
                value: $ratio,
                reason: \sprintf('Similarity ratio: %.2f (using similar_text for long strings)', $ratio),
            );
        }

        $ratio = 1.0 - ($distance / $maxLength);

        return new ScoreResult(
            name: $this->name,
            value: $ratio,
            reason: \sprintf('Levenshtein ratio: %.2f (distance: %d, max length: %d)', $ratio, $distance, $maxLength),
        );
    }
}
