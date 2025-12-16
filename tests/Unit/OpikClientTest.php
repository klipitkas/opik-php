<?php

declare(strict_types=1);

namespace Opik\Tests\Unit;

use InvalidArgumentException;
use Opik\OpikClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OpikClientTest extends TestCase
{
    #[Test]
    public function shouldThrowOnEmptyTraceName(): void
    {
        $client = new OpikClient(baseUrl: 'http://localhost:5173/api/');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Trace name cannot be empty');

        $client->trace(name: '');
    }

    #[Test]
    public function shouldThrowOnWhitespaceTraceName(): void
    {
        $client = new OpikClient(baseUrl: 'http://localhost:5173/api/');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Trace name cannot be empty');

        $client->trace(name: '   ');
    }

    #[Test]
    public function shouldThrowOnEmptySpanName(): void
    {
        $client = new OpikClient(baseUrl: 'http://localhost:5173/api/');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Span name cannot be empty');

        $client->span(name: '', traceId: 'trace-123');
    }

    #[Test]
    public function shouldThrowOnEmptySpanTraceId(): void
    {
        $client = new OpikClient(baseUrl: 'http://localhost:5173/api/');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Trace ID cannot be empty');

        $client->span(name: 'test-span', traceId: '');
    }

    #[Test]
    public function shouldThrowOnEmptyDatasetName(): void
    {
        $client = new OpikClient(baseUrl: 'http://localhost:5173/api/');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Dataset name cannot be empty');

        $client->createDataset(name: '');
    }

    #[Test]
    public function shouldThrowOnEmptyExperimentName(): void
    {
        $client = new OpikClient(baseUrl: 'http://localhost:5173/api/');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Experiment name cannot be empty');

        $client->createExperiment(name: '', datasetName: 'test-dataset');
    }

    #[Test]
    public function shouldThrowOnEmptyExperimentDatasetName(): void
    {
        $client = new OpikClient(baseUrl: 'http://localhost:5173/api/');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Dataset name is required to create an experiment');

        $client->createExperiment(name: 'test-experiment', datasetName: '');
    }

    #[Test]
    public function shouldThrowOnEmptyPromptName(): void
    {
        $client = new OpikClient(baseUrl: 'http://localhost:5173/api/');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Prompt name cannot be empty');

        $client->createPrompt(name: '', template: 'Hello {{name}}');
    }

    #[Test]
    public function shouldThrowOnEmptyPromptTemplate(): void
    {
        $client = new OpikClient(baseUrl: 'http://localhost:5173/api/');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Prompt template cannot be empty');

        $client->createPrompt(name: 'test-prompt', template: '');
    }

    #[Test]
    public function shouldThrowOnEmptyTraceIdForGetContent(): void
    {
        $client = new OpikClient(baseUrl: 'http://localhost:5173/api/');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Trace ID cannot be empty');

        $client->getTraceContent(traceId: '');
    }

    #[Test]
    public function shouldThrowOnEmptySpanIdForGetContent(): void
    {
        $client = new OpikClient(baseUrl: 'http://localhost:5173/api/');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Span ID cannot be empty');

        $client->getSpanContent(spanId: '');
    }

    #[Test]
    public function shouldCreateTraceWithThreadId(): void
    {
        $client = new OpikClient(baseUrl: 'http://localhost:5173/api/');

        $trace = $client->trace(
            name: 'test-trace',
            threadId: 'thread-123',
        );

        self::assertSame('thread-123', $trace->getThreadId());
    }

    #[Test]
    public function shouldCreateTraceWithDefaultProject(): void
    {
        $client = new OpikClient(
            baseUrl: 'http://localhost:5173/api/',
            projectName: 'my-project',
        );

        $trace = $client->trace(name: 'test-trace');

        self::assertSame('my-project', $trace->getProjectName());
    }

    #[Test]
    public function shouldThrowOnEmptyExperimentIdForUpdate(): void
    {
        $client = new OpikClient(baseUrl: 'http://localhost:5173/api/');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Experiment ID cannot be empty');

        $client->updateExperiment(id: '');
    }

    #[Test]
    public function shouldThrowOnEmptyExperimentIdForGetById(): void
    {
        $client = new OpikClient(baseUrl: 'http://localhost:5173/api/');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Experiment ID cannot be empty');

        $client->getExperimentById(id: '');
    }

    #[Test]
    public function shouldThrowOnEmptyProjectIdForGet(): void
    {
        $client = new OpikClient(baseUrl: 'http://localhost:5173/api/');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Project ID cannot be empty');

        $client->getProject(id: '');
    }

    #[Test]
    public function shouldGenerateProjectUrl(): void
    {
        $client = new OpikClient(
            baseUrl: 'http://localhost:5173/api/',
            projectName: 'test-project',
            workspace: 'test-workspace',
        );

        $url = $client->getProjectUrl();

        self::assertSame('http://localhost:5173/test-workspace/projects/test-project/traces', $url);
    }

    #[Test]
    public function shouldGenerateProjectUrlWithCustomName(): void
    {
        $client = new OpikClient(
            baseUrl: 'http://localhost:5173/api/',
            projectName: 'default-project',
            workspace: 'my-workspace',
        );

        $url = $client->getProjectUrl('custom-project');

        self::assertSame('http://localhost:5173/my-workspace/projects/custom-project/traces', $url);
    }

    #[Test]
    public function shouldThrowOnEmptyPromptIdsForDelete(): void
    {
        $client = new OpikClient(baseUrl: 'http://localhost:5173/api/');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Prompt IDs array cannot be empty');

        $client->deletePrompts(ids: []);
    }

    #[Test]
    public function shouldThrowOnEmptyPromptNameForHistory(): void
    {
        $client = new OpikClient(baseUrl: 'http://localhost:5173/api/');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Prompt name cannot be empty');

        $client->getPromptHistory(name: '');
    }

    #[Test]
    public function shouldThrowOnEmptyTraceIdForDeleteFeedback(): void
    {
        $client = new OpikClient(baseUrl: 'http://localhost:5173/api/');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Trace ID cannot be empty');

        $client->deleteTraceFeedbackScore(traceId: '', name: 'score');
    }

    #[Test]
    public function shouldThrowOnEmptyNameForDeleteTraceFeedback(): void
    {
        $client = new OpikClient(baseUrl: 'http://localhost:5173/api/');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Feedback score name cannot be empty');

        $client->deleteTraceFeedbackScore(traceId: 'trace-123', name: '');
    }

    #[Test]
    public function shouldThrowOnEmptySpanIdForDeleteFeedback(): void
    {
        $client = new OpikClient(baseUrl: 'http://localhost:5173/api/');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Span ID cannot be empty');

        $client->deleteSpanFeedbackScore(spanId: '', name: 'score');
    }

    #[Test]
    public function shouldThrowOnEmptyNameForDeleteSpanFeedback(): void
    {
        $client = new OpikClient(baseUrl: 'http://localhost:5173/api/');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Feedback score name cannot be empty');

        $client->deleteSpanFeedbackScore(spanId: 'span-123', name: '');
    }
}
