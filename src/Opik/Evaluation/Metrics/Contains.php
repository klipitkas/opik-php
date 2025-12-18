<?php

declare(strict_types=1);

namespace Opik\Evaluation\Metrics;

/**
 * Contains metric - checks if the actual output contains the expected substring.
 */
final class Contains extends BaseMetric
{
    public function __construct(
        string $name = 'contains',
        private readonly bool $caseSensitive = true,
    ) {
        parent::__construct($name);
    }

    /**
     * Calculates a score based on whether output contains expected.
     *
     * @param array<string, mixed> $input Must contain 'output' and 'expected' keys
     *
     * @return ScoreResult Score result (1.0 if contains, 0.0 if not)
     */
    public function score(array $input): ScoreResult
    {
        $output = (string) ($input['output'] ?? '');
        $expected = (string) ($input['expected'] ?? '');

        if ($this->caseSensitive) {
            $contains = str_contains($output, $expected);
        } else {
            $contains = str_contains(strtolower($output), strtolower($expected));
        }

        return new ScoreResult(
            name: $this->name,
            value: $contains ? 1.0 : 0.0,
            reason: $contains
                ? 'Contains: Output contains the expected substring'
                : 'Contains: Output does not contain the expected substring',
        );
    }
}
