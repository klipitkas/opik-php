<?php

declare(strict_types=1);

namespace Opik\Evaluation;

use Opik\Evaluation\Metrics\ScoreResult;

/**
 * Represents the result of a single test case evaluation.
 */
final class EvaluationTestResult
{
    /**
     * @param string $datasetItemId ID of the dataset item
     * @param string|null $traceId ID of the trace (if tracing enabled)
     * @param array<string, mixed> $taskOutput Output from the task execution
     * @param array<int, ScoreResult> $scoreResults Results from all metrics
     */
    public function __construct(
        public readonly string $datasetItemId,
        public readonly ?string $traceId,
        public readonly array $taskOutput,
        public readonly array $scoreResults,
    ) {
    }

    /**
     * Get a specific score by metric name.
     */
    public function getScore(string $metricName): ?ScoreResult
    {
        foreach ($this->scoreResults as $score) {
            if ($score->name === $metricName) {
                return $score;
            }
        }

        return null;
    }
}
