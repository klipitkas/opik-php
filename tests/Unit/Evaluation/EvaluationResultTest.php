<?php

declare(strict_types=1);

namespace Opik\Tests\Unit\Evaluation;

use Opik\Evaluation\EvaluationResult;
use Opik\Evaluation\EvaluationTestResult;
use Opik\Evaluation\Metrics\ScoreResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EvaluationResultTest extends TestCase
{
    #[Test]
    public function shouldReturnCorrectCount(): void
    {
        $result = new EvaluationResult(
            experimentId: 'exp-123',
            experimentName: 'test-experiment',
            testResults: [
                new EvaluationTestResult('item-1', 'trace-1', [], []),
                new EvaluationTestResult('item-2', 'trace-2', [], []),
                new EvaluationTestResult('item-3', 'trace-3', [], []),
            ],
            durationSeconds: 1.5,
        );

        self::assertSame(3, $result->count());
    }

    #[Test]
    public function shouldCalculateAverageScoreForMetric(): void
    {
        $result = new EvaluationResult(
            experimentId: 'exp-123',
            experimentName: 'test-experiment',
            testResults: [
                new EvaluationTestResult('item-1', 'trace-1', [], [
                    new ScoreResult('accuracy', 0.8),
                    new ScoreResult('relevance', 0.9),
                ]),
                new EvaluationTestResult('item-2', 'trace-2', [], [
                    new ScoreResult('accuracy', 0.6),
                    new ScoreResult('relevance', 0.7),
                ]),
            ],
            durationSeconds: 1.0,
        );

        self::assertEqualsWithDelta(0.7, $result->getAverageScore('accuracy'), 0.0001);
        self::assertEqualsWithDelta(0.8, $result->getAverageScore('relevance'), 0.0001);
    }

    #[Test]
    public function shouldReturnNullForNonExistentMetric(): void
    {
        $result = new EvaluationResult(
            experimentId: 'exp-123',
            experimentName: 'test-experiment',
            testResults: [
                new EvaluationTestResult('item-1', 'trace-1', [], [
                    new ScoreResult('accuracy', 0.8),
                ]),
            ],
            durationSeconds: 1.0,
        );

        self::assertNull($result->getAverageScore('non_existent'));
    }

    #[Test]
    public function shouldGetAllAverageScores(): void
    {
        $result = new EvaluationResult(
            experimentId: 'exp-123',
            experimentName: 'test-experiment',
            testResults: [
                new EvaluationTestResult('item-1', 'trace-1', [], [
                    new ScoreResult('accuracy', 1.0),
                    new ScoreResult('relevance', 0.5),
                ]),
                new EvaluationTestResult('item-2', 'trace-2', [], [
                    new ScoreResult('accuracy', 0.5),
                    new ScoreResult('relevance', 1.0),
                ]),
            ],
            durationSeconds: 1.0,
        );

        $averages = $result->getAverageScores();

        self::assertCount(2, $averages);
        self::assertEqualsWithDelta(0.75, $averages['accuracy'], 0.0001);
        self::assertEqualsWithDelta(0.75, $averages['relevance'], 0.0001);
    }

    #[Test]
    public function shouldHandleEmptyResults(): void
    {
        $result = new EvaluationResult(
            experimentId: 'exp-123',
            experimentName: 'test-experiment',
            testResults: [],
            durationSeconds: 0.1,
        );

        self::assertSame(0, $result->count());
        self::assertSame([], $result->getAverageScores());
    }
}
