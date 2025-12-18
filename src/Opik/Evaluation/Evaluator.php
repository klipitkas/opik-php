<?php

declare(strict_types=1);

namespace Opik\Evaluation;

use InvalidArgumentException;
use Opik\Dataset\Dataset;
use Opik\Evaluation\Metrics\BaseMetric;
use Opik\Evaluation\Metrics\ScoreResult;
use Opik\Experiment\Experiment;
use Opik\Experiment\ExperimentItem;
use Opik\OpikClient;
use Throwable;

/**
 * Evaluator for running experiments against datasets with metrics.
 */
final class Evaluator
{
    public function __construct(
        private readonly OpikClient $client,
    ) {
    }

    /**
     * Evaluate a task function against a dataset using specified metrics.
     *
     * @param Dataset $dataset The dataset to evaluate against
     * @param callable $task The task function that processes each dataset item
     *                       Signature: fn(array $item): array
     * @param array<int, BaseMetric> $scoringMetrics Metrics to evaluate the task output
     * @param string|null $experimentName Optional name for the experiment
     * @param array<string, string> $scoringKeyMapping Optional mapping of keys for scoring
     * @param int|null $nbSamples Optional limit on number of samples to evaluate
     *
     * @throws InvalidArgumentException If required parameters are missing
     */
    public function evaluate(
        Dataset $dataset,
        callable $task,
        array $scoringMetrics = [],
        ?string $experimentName = null,
        array $scoringKeyMapping = [],
        ?int $nbSamples = null,
    ): EvaluationResult {
        $startTime = microtime(true);

        // Create experiment for this evaluation
        $experiment = $this->client->createExperiment(
            name: $experimentName ?? 'evaluation-' . date('Y-m-d-H-i-s'),
            datasetName: $dataset->name,
        );

        // Get dataset items
        $items = $dataset->getItems(page: 1, size: $nbSamples ?? 1000);

        if ($nbSamples !== null && $nbSamples < \count($items)) {
            $items = \array_slice($items, 0, $nbSamples);
        }

        $testResults = [];
        $experimentItems = [];

        foreach ($items as $item) {
            $itemData = $item->getContent();

            // Execute task
            $taskOutput = [];
            $traceId = null;

            try {
                // Create trace for this evaluation task
                $trace = $this->client->trace(
                    name: 'evaluation_task',
                    input: $itemData,
                );
                $traceId = $trace->getId();

                // Execute the task
                $taskOutput = $task($itemData);

                // End trace with output
                $trace->update(output: $taskOutput);
                $trace->end();
            } catch (Throwable $e) {
                // Log error but continue with other items
                $taskOutput = ['error' => $e->getMessage()];
            }

            // Prepare scoring inputs (combine dataset item with task output)
            $scoringInputs = $this->prepareScoringInputs(
                $itemData,
                $taskOutput,
                $scoringKeyMapping,
            );

            // Calculate scores
            $scoreResults = $this->calculateScores($scoringMetrics, $scoringInputs);

            // Log feedback scores to trace
            if ($traceId !== null) {
                foreach ($scoreResults as $score) {
                    $this->client->logTracesFeedbackScores([
                        \Opik\Feedback\FeedbackScore::forTrace(
                            $traceId,
                            $score->name,
                            value: $score->value,
                            reason: $score->reason,
                        ),
                    ]);
                }
            }

            $testResults[] = new EvaluationTestResult(
                datasetItemId: $item->id,
                traceId: $traceId,
                taskOutput: $taskOutput,
                scoreResults: $scoreResults,
            );

            // Create experiment item
            $experimentItems[] = new ExperimentItem(
                datasetItemId: $item->id,
                traceId: $traceId,
                output: $taskOutput,
                feedbackScores: array_map(
                    static fn (ScoreResult $s) => [
                        'name' => $s->name,
                        'value' => $s->value,
                        'reason' => $s->reason,
                    ],
                    $scoreResults,
                ),
            );
        }

        // Log experiment items
        if ($experimentItems !== []) {
            $experiment->logItems($experimentItems);
        }

        // Flush to ensure all data is sent
        $this->client->flush();

        $endTime = microtime(true);

        return new EvaluationResult(
            experimentId: $experiment->id,
            experimentName: $experiment->name,
            testResults: $testResults,
            durationSeconds: $endTime - $startTime,
        );
    }

    /**
     * Prepare inputs for scoring by combining dataset item and task output.
     *
     * @param array<string, mixed> $datasetItem
     * @param array<string, mixed> $taskOutput
     * @param array<string, string> $keyMapping
     *
     * @return array<string, mixed>
     */
    private function prepareScoringInputs(
        array $datasetItem,
        array $taskOutput,
        array $keyMapping,
    ): array {
        $combined = array_merge($datasetItem, $taskOutput);

        if ($keyMapping === []) {
            return $combined;
        }

        // Apply key mapping
        foreach ($keyMapping as $targetKey => $sourceKey) {
            if (isset($combined[$sourceKey])) {
                $combined[$targetKey] = $combined[$sourceKey];
            }
        }

        return $combined;
    }

    /**
     * Calculate scores for all metrics.
     *
     * @param array<int, BaseMetric> $metrics
     * @param array<string, mixed> $inputs
     *
     * @return array<int, ScoreResult>
     */
    private function calculateScores(array $metrics, array $inputs): array
    {
        $results = [];

        foreach ($metrics as $metric) {
            try {
                $scoreResult = $metric->score($inputs);

                if (\is_array($scoreResult)) {
                    foreach ($scoreResult as $r) {
                        $results[] = $r;
                    }
                } else {
                    $results[] = $scoreResult;
                }
            } catch (Throwable $e) {
                // Log error but continue with other metrics
                $results[] = new ScoreResult(
                    name: $metric->name,
                    value: 0.0,
                    reason: 'Metric failed: ' . $e->getMessage(),
                );
            }
        }

        return $results;
    }
}
