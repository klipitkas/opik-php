<?php

declare(strict_types=1);

namespace Opik\Evaluation\Metrics;

/**
 * Equals metric - checks if the actual output equals the expected output.
 *
 * Unlike ExactMatch which uses strict comparison (===), this metric
 * can optionally use loose comparison (==) for type-coerced matching.
 */
final class Equals extends BaseMetric
{
    public function __construct(
        string $name = 'equals',
        private readonly bool $strict = true,
    ) {
        parent::__construct($name);
    }

    /**
     * Calculates a score based on equality between output and expected.
     *
     * @param array<string, mixed> $input Must contain 'output' and 'expected' keys
     *
     * @return ScoreResult Score result (1.0 for equal, 0.0 for not equal)
     */
    public function score(array $input): ScoreResult
    {
        $output = $input['output'] ?? null;
        $expected = $input['expected'] ?? null;

        $isEqual = $this->strict
            ? $output === $expected
            : $output == $expected;

        $comparisonType = $this->strict ? 'strict' : 'loose';

        return new ScoreResult(
            name: $this->name,
            value: $isEqual ? 1.0 : 0.0,
            reason: $isEqual
                ? \sprintf('Equal (%s comparison)', $comparisonType)
                : \sprintf('Not equal (%s comparison)', $comparisonType),
        );
    }
}
