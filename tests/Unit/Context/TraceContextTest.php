<?php

declare(strict_types=1);

namespace Opik\Tests\Unit\Context;

use Opik\Api\HttpClientInterface;
use Opik\Config\Config;
use Opik\Context\TraceContext;
use Opik\Message\BatchQueue;
use Opik\Tracer\Span;
use Opik\Tracer\Trace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TraceContextTest extends TestCase
{
    private BatchQueue $batchQueue;

    protected function setUp(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $config = new Config();
        $this->batchQueue = new BatchQueue($httpClient, $config);
        TraceContext::clear();
    }

    protected function tearDown(): void
    {
        TraceContext::clear();
    }

    #[Test]
    public function shouldSetAndGetCurrentTrace(): void
    {
        $trace = new Trace(
            batchQueue: $this->batchQueue,
            name: 'test-trace',
            projectName: 'test-project',
        );

        TraceContext::setCurrentTrace($trace);

        self::assertSame($trace, TraceContext::getCurrentTrace());
        self::assertTrue(TraceContext::hasActiveTrace());
    }

    #[Test]
    public function shouldReturnNullWhenNoActiveTrace(): void
    {
        self::assertNull(TraceContext::getCurrentTrace());
        self::assertFalse(TraceContext::hasActiveTrace());
    }

    #[Test]
    public function shouldSetAndGetCurrentSpan(): void
    {
        $span = new Span(
            batchQueue: $this->batchQueue,
            traceId: 'trace-123',
            name: 'test-span',
            projectName: 'test-project',
        );

        TraceContext::setCurrentSpan($span);

        self::assertSame($span, TraceContext::getCurrentSpan());
        self::assertTrue(TraceContext::hasActiveSpan());
    }

    #[Test]
    public function shouldPushAndPopSpans(): void
    {
        $span1 = new Span(
            batchQueue: $this->batchQueue,
            traceId: 'trace-123',
            name: 'span-1',
            projectName: 'test-project',
        );

        $span2 = new Span(
            batchQueue: $this->batchQueue,
            traceId: 'trace-123',
            name: 'span-2',
            projectName: 'test-project',
        );

        TraceContext::pushSpan($span1);
        self::assertSame($span1, TraceContext::getCurrentSpan());

        TraceContext::pushSpan($span2);
        self::assertSame($span2, TraceContext::getCurrentSpan());

        $popped = TraceContext::popSpan();
        self::assertSame($span2, $popped);
        self::assertSame($span1, TraceContext::getCurrentSpan());

        TraceContext::popSpan();
        self::assertNull(TraceContext::getCurrentSpan());
    }

    #[Test]
    public function shouldClearAllContext(): void
    {
        $trace = new Trace(
            batchQueue: $this->batchQueue,
            name: 'test-trace',
            projectName: 'test-project',
        );

        $span = new Span(
            batchQueue: $this->batchQueue,
            traceId: 'trace-123',
            name: 'test-span',
            projectName: 'test-project',
        );

        TraceContext::setCurrentTrace($trace);
        TraceContext::pushSpan($span);

        TraceContext::clear();

        self::assertNull(TraceContext::getCurrentTrace());
        self::assertNull(TraceContext::getCurrentSpan());
        self::assertFalse(TraceContext::hasActiveTrace());
        self::assertFalse(TraceContext::hasActiveSpan());
    }
}
