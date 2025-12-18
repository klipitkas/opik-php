<?php

declare(strict_types=1);

namespace Opik\Tests\Unit\Evaluation;

use Opik\Evaluation\EvaluationTestResult;
use Opik\Evaluation\Metrics\ScoreResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EvaluationTestResultTest extends TestCase
{
    #[Test]
    public function shouldGetScoreByName(): void
    {
        $result = new EvaluationTestResult(
            datasetItemId: 'item-1',
            traceId: 'trace-1',
            taskOutput: ['output' => 'hello'],
            scoreResults: [
                new ScoreResult('accuracy', 0.9, 'High accuracy'),
                new ScoreResult('relevance', 0.8),
            ],
        );

        $accuracy = $result->getScore('accuracy');
        self::assertNotNull($accuracy);
        self::assertSame('accuracy', $accuracy->name);
        self::assertSame(0.9, $accuracy->value);
        self::assertSame('High accuracy', $accuracy->reason);
    }

    #[Test]
    public function shouldReturnNullForNonExistentScore(): void
    {
        $result = new EvaluationTestResult(
            datasetItemId: 'item-1',
            traceId: 'trace-1',
            taskOutput: [],
            scoreResults: [
                new ScoreResult('accuracy', 0.9),
            ],
        );

        self::assertNull($result->getScore('non_existent'));
    }

    #[Test]
    public function shouldStoreTaskOutput(): void
    {
        $output = ['response' => 'Hello, world!', 'confidence' => 0.95];

        $result = new EvaluationTestResult(
            datasetItemId: 'item-1',
            traceId: 'trace-1',
            taskOutput: $output,
            scoreResults: [],
        );

        self::assertSame($output, $result->taskOutput);
    }

    #[Test]
    public function shouldHandleNullTraceId(): void
    {
        $result = new EvaluationTestResult(
            datasetItemId: 'item-1',
            traceId: null,
            taskOutput: [],
            scoreResults: [],
        );

        self::assertNull($result->traceId);
    }
}
