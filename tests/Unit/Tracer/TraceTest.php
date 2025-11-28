<?php

declare(strict_types=1);

namespace Opik\Tests\Unit\Tracer;

use Opik\Api\HttpClientInterface;
use Opik\Config\Config;
use Opik\Message\BatchQueue;
use Opik\Tracer\ErrorInfo;
use Opik\Tracer\SpanType;
use Opik\Tracer\Trace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TraceTest extends TestCase
{
    private BatchQueue $batchQueue;

    protected function setUp(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $config = new Config();
        $this->batchQueue = new BatchQueue($httpClient, $config);
    }

    #[Test]
    public function shouldCreateTraceWithName(): void
    {
        $trace = new Trace(
            batchQueue: $this->batchQueue,
            name: 'test-trace',
            projectName: 'test-project',
        );

        self::assertSame('test-trace', $trace->getName());
        self::assertSame('test-project', $trace->getProjectName());
        self::assertNotEmpty($trace->getId());
    }

    #[Test]
    public function shouldCreateTraceWithCustomId(): void
    {
        $trace = new Trace(
            batchQueue: $this->batchQueue,
            name: 'test-trace',
            projectName: 'test-project',
            id: 'custom-id',
        );

        self::assertSame('custom-id', $trace->getId());
    }

    #[Test]
    public function shouldCreateSpanFromTrace(): void
    {
        $trace = new Trace(
            batchQueue: $this->batchQueue,
            name: 'test-trace',
            projectName: 'test-project',
        );

        $span = $trace->span(
            name: 'test-span',
            type: SpanType::LLM,
        );

        self::assertSame('test-span', $span->getName());
        self::assertSame(SpanType::LLM, $span->getType());
        self::assertSame($trace->getId(), $span->getTraceId());
    }

    #[Test]
    public function shouldUpdateTraceOutput(): void
    {
        $trace = new Trace(
            batchQueue: $this->batchQueue,
            name: 'test-trace',
            projectName: 'test-project',
        );

        $trace->update(output: ['result' => 'success']);

        $array = $trace->toArray();
        self::assertSame(['result' => 'success'], $array['output']);
    }

    #[Test]
    public function shouldMergeMetadata(): void
    {
        $trace = new Trace(
            batchQueue: $this->batchQueue,
            name: 'test-trace',
            projectName: 'test-project',
            metadata: ['key1' => 'value1'],
        );

        $trace->update(metadata: ['key2' => 'value2']);

        $array = $trace->toArray();
        self::assertSame(['key1' => 'value1', 'key2' => 'value2'], $array['metadata']);
    }

    #[Test]
    public function shouldEndTraceOnlyOnce(): void
    {
        $trace = new Trace(
            batchQueue: $this->batchQueue,
            name: 'test-trace',
            projectName: 'test-project',
        );

        $trace->end();
        $firstEndTime = $trace->toArray()['end_time'];

        usleep(1000);

        $trace->end();
        $secondEndTime = $trace->toArray()['end_time'];

        self::assertSame($firstEndTime, $secondEndTime);
    }

    #[Test]
    public function shouldIncludeErrorInfoInArray(): void
    {
        $trace = new Trace(
            batchQueue: $this->batchQueue,
            name: 'test-trace',
            projectName: 'test-project',
        );

        $errorInfo = new ErrorInfo(
            message: 'Test error',
            exceptionType: 'RuntimeException',
            traceback: 'stack trace',
        );

        $trace->update(errorInfo: $errorInfo);

        $array = $trace->toArray();
        self::assertSame('Test error', $array['error_info']['message']);
        self::assertSame('RuntimeException', $array['error_info']['exception_type']);
    }

    #[Test]
    public function shouldConvertToArrayWithAllFields(): void
    {
        $trace = new Trace(
            batchQueue: $this->batchQueue,
            name: 'test-trace',
            projectName: 'test-project',
            input: ['prompt' => 'hello'],
            metadata: ['model' => 'gpt-4'],
            tags: ['test', 'unit'],
        );

        $trace->update(output: ['response' => 'world']);
        $trace->end();

        $array = $trace->toArray();

        self::assertArrayHasKey('id', $array);
        self::assertSame('test-trace', $array['name']);
        self::assertSame('test-project', $array['project_name']);
        self::assertArrayHasKey('start_time', $array);
        self::assertArrayHasKey('end_time', $array);
        self::assertSame(['prompt' => 'hello'], $array['input']);
        self::assertSame(['response' => 'world'], $array['output']);
        self::assertSame(['model' => 'gpt-4'], $array['metadata']);
        self::assertSame(['test', 'unit'], $array['tags']);
    }

    #[Test]
    public function shouldIncludeThreadIdInArray(): void
    {
        $trace = new Trace(
            batchQueue: $this->batchQueue,
            name: 'test-trace',
            projectName: 'test-project',
            threadId: 'thread-123',
        );

        self::assertSame('thread-123', $trace->getThreadId());
        self::assertSame('thread-123', $trace->toArray()['thread_id']);
    }

    #[Test]
    public function shouldOmitThreadIdWhenNull(): void
    {
        $trace = new Trace(
            batchQueue: $this->batchQueue,
            name: 'test-trace',
            projectName: 'test-project',
        );

        self::assertNull($trace->getThreadId());
        self::assertArrayNotHasKey('thread_id', $trace->toArray());
    }
}
