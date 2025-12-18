<?php

declare(strict_types=1);

namespace Opik\Tests\Unit\Tracer;

use Opik\Api\HttpClientInterface;
use Opik\Message\BatchQueue;
use Opik\Tracer\ErrorInfo;
use Opik\Tracer\Span;
use Opik\Tracer\SpanType;
use Opik\Tracer\Usage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SpanTest extends TestCase
{
    private BatchQueue $batchQueue;

    protected function setUp(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $this->batchQueue = new BatchQueue($httpClient);
    }

    #[Test]
    public function shouldCreateSpanWithRequiredFields(): void
    {
        $span = new Span(
            batchQueue: $this->batchQueue,
            traceId: 'trace-123',
            name: 'test-span',
            projectName: 'test-project',
        );

        self::assertSame('test-span', $span->getName());
        self::assertSame('trace-123', $span->getTraceId());
        self::assertSame(SpanType::GENERAL, $span->getType());
        self::assertNotEmpty($span->getId());
    }

    #[Test]
    public function shouldCreateSpanWithLlmType(): void
    {
        $span = new Span(
            batchQueue: $this->batchQueue,
            traceId: 'trace-123',
            name: 'llm-span',
            projectName: 'test-project',
            type: SpanType::LLM,
        );

        self::assertSame(SpanType::LLM, $span->getType());
    }

    #[Test]
    public function shouldCreateChildSpan(): void
    {
        $parentSpan = new Span(
            batchQueue: $this->batchQueue,
            traceId: 'trace-123',
            name: 'parent-span',
            projectName: 'test-project',
        );

        $childSpan = $parentSpan->span(
            name: 'child-span',
            type: SpanType::TOOL,
        );

        self::assertSame('child-span', $childSpan->getName());
        self::assertSame(SpanType::TOOL, $childSpan->getType());
        self::assertSame('trace-123', $childSpan->getTraceId());

        $childArray = $childSpan->toArray();
        self::assertSame($parentSpan->getId(), $childArray['parent_span_id']);
    }

    #[Test]
    public function shouldUpdateSpanWithUsage(): void
    {
        $span = new Span(
            batchQueue: $this->batchQueue,
            traceId: 'trace-123',
            name: 'test-span',
            projectName: 'test-project',
            type: SpanType::LLM,
        );

        $usage = new Usage(
            promptTokens: 100,
            completionTokens: 50,
            totalTokens: 150,
        );

        $span->update(
            model: 'gpt-4',
            provider: 'openai',
            usage: $usage,
        );

        $array = $span->toArray();
        self::assertSame('gpt-4', $array['model']);
        self::assertSame('openai', $array['provider']);
        self::assertSame(100, $array['usage']['prompt_tokens']);
        self::assertSame(50, $array['usage']['completion_tokens']);
        self::assertSame(150, $array['usage']['total_tokens']);
    }

    #[Test]
    public function shouldEndSpanOnlyOnce(): void
    {
        $span = new Span(
            batchQueue: $this->batchQueue,
            traceId: 'trace-123',
            name: 'test-span',
            projectName: 'test-project',
        );

        $span->end();
        $firstEndTime = $span->toArray()['end_time'];

        usleep(1000);

        $span->end();
        $secondEndTime = $span->toArray()['end_time'];

        self::assertSame($firstEndTime, $secondEndTime);
    }

    #[Test]
    public function shouldIncludeErrorInfoInArray(): void
    {
        $span = new Span(
            batchQueue: $this->batchQueue,
            traceId: 'trace-123',
            name: 'test-span',
            projectName: 'test-project',
        );

        $errorInfo = new ErrorInfo(
            message: 'API error',
            exceptionType: 'ApiException',
            traceback: 'trace',
        );

        $span->update(errorInfo: $errorInfo);

        $array = $span->toArray();
        self::assertSame('API error', $array['error_info']['message']);
    }

    #[Test]
    public function shouldConvertToArrayWithAllFields(): void
    {
        $span = new Span(
            batchQueue: $this->batchQueue,
            traceId: 'trace-123',
            name: 'test-span',
            projectName: 'test-project',
            parentSpanId: 'parent-456',
            type: SpanType::LLM,
            input: ['messages' => [['role' => 'user', 'content' => 'hello']]],
            metadata: ['temperature' => 0.7],
            tags: ['production'],
        );

        $span->update(
            output: ['response' => 'hi'],
            model: 'gpt-4',
            provider: 'openai',
        );
        $span->end();

        $array = $span->toArray();

        self::assertArrayHasKey('id', $array);
        self::assertSame('trace-123', $array['trace_id']);
        self::assertSame('parent-456', $array['parent_span_id']);
        self::assertSame('test-span', $array['name']);
        self::assertSame('llm', $array['type']);
        self::assertSame('test-project', $array['project_name']);
        self::assertArrayHasKey('start_time', $array);
        self::assertArrayHasKey('end_time', $array);
        self::assertSame('gpt-4', $array['model']);
        self::assertSame('openai', $array['provider']);
    }

    #[Test]
    public function shouldIncludeTotalCostInArray(): void
    {
        $span = new Span(
            batchQueue: $this->batchQueue,
            traceId: 'trace-123',
            name: 'test-span',
            projectName: 'test-project',
        );

        $span->update(totalCost: 0.0025);

        $array = $span->toArray();
        self::assertSame(0.0025, $array['total_estimated_cost']);
    }

    #[Test]
    public function shouldOmitTotalCostWhenNull(): void
    {
        $span = new Span(
            batchQueue: $this->batchQueue,
            traceId: 'trace-123',
            name: 'test-span',
            projectName: 'test-project',
        );

        self::assertArrayNotHasKey('total_estimated_cost', $span->toArray());
    }
}
