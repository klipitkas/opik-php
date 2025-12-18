<?php

declare(strict_types=1);

namespace Opik\Evaluation\Metrics;

/**
 * Base class for all evaluation metrics.
 */
abstract class BaseMetric
{
    public function __construct(
        public readonly string $name,
    ) {
    }

    /**
     * Calculate the metric score for the given input.
     *
     * @param array<string, mixed> $input The input data to evaluate
     *
     * @return ScoreResult|array<ScoreResult> The evaluation result(s)
     */
    abstract public function score(array $input): ScoreResult|array;
}
