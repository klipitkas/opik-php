<?php

declare(strict_types=1);

namespace Opik\Evaluation;

/**
 * Represents the result of an evaluation experiment.
 */
final class EvaluationResult
{
    /**
     * @param string $experimentId ID of the experiment
     * @param string|null $experimentName Name of the experiment
     * @param array<int, EvaluationTestResult> $testResults Test results for all evaluated items
     * @param float $durationSeconds Total duration of the evaluation in seconds
     */
    public function __construct(
        public readonly string $experimentId,
        public readonly ?string $experimentName,
        public readonly array $testResults,
        public readonly float $durationSeconds,
    ) {
    }

    /**
     * Get the number of evaluated items.
     */
    public function count(): int
    {
        return \count($this->testResults);
    }

    /**
     * Get the average score for a specific metric.
     */
    public function getAverageScore(string $metricName): ?float
    {
        $scores = [];

        foreach ($this->testResults as $result) {
            foreach ($result->scoreResults as $score) {
                if ($score->name === $metricName) {
                    $scores[] = $score->value;
                }
            }
        }

        if ($scores === []) {
            return null;
        }

        return array_sum($scores) / \count($scores);
    }

    /**
     * Get all average scores grouped by metric name.
     *
     * @return array<string, float>
     */
    public function getAverageScores(): array
    {
        $scoresByMetric = [];

        foreach ($this->testResults as $result) {
            foreach ($result->scoreResults as $score) {
                if (! isset($scoresByMetric[$score->name])) {
                    $scoresByMetric[$score->name] = [];
                }
                $scoresByMetric[$score->name][] = $score->value;
            }
        }

        $averages = [];
        foreach ($scoresByMetric as $name => $scores) {
            $averages[$name] = array_sum($scores) / \count($scores);
        }

        return $averages;
    }
}
