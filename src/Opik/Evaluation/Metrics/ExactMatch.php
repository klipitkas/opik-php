<?php

declare(strict_types=1);

namespace Opik\Evaluation\Metrics;

/**
 * ExactMatch metric - checks if the actual output exactly matches the expected output.
 */
final class ExactMatch extends BaseMetric
{
    public function __construct(string $name = 'exact_match')
    {
        parent::__construct($name);
    }

    /**
     * Calculates a score based on exact match between output and expected.
     *
     * @param array<string, mixed> $input Must contain 'output' and 'expected' keys
     *
     * @return ScoreResult Score result (1.0 for match, 0.0 for no match)
     */
    public function score(array $input): ScoreResult
    {
        $output = $input['output'] ?? null;
        $expected = $input['expected'] ?? null;

        $isMatch = $output === $expected;

        return new ScoreResult(
            name: $this->name,
            value: $isMatch ? 1.0 : 0.0,
            reason: $isMatch ? 'Exact match: Match' : 'Exact match: No match',
        );
    }
}
