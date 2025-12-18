<?php

declare(strict_types=1);

namespace Opik\Tests\Unit\Message;

use Opik\Api\HttpClientInterface;
use Opik\Config\Config;
use Opik\Message\BatchQueue;
use Opik\Message\Message;
use Opik\Message\MessageType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class BatchQueueTest extends TestCase
{
    private HttpClientInterface&\PHPUnit\Framework\MockObject\MockObject $httpClient;

    private BatchQueue $batchQueue;

    /** @var array<int, array<string, mixed>> */
    private array $capturedData = [];

    /** @var int */
    private int $flushCount = 0;

    protected function setUp(): void
    {
        $this->flushCount = 0;
        $this->capturedData = [];

        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->httpClient->method('post')->willReturnCallback(function ($endpoint, $data) {
            $this->capturedData = $data['traces'] ?? $data['spans'] ?? [];
            $this->flushCount++;

            return [];
        });

        $this->batchQueue = new BatchQueue($this->httpClient);
    }

    /**
     * @param array<string, mixed>|null $output
     */
    private function traceMessage(string $id, MessageType $type, ?array $output = null): Message
    {
        $data = ['id' => $id, 'name' => 'test', 'project_name' => 'test', 'start_time' => '2024-01-01T00:00:00Z'];
        if ($output !== null) {
            $data['output'] = $output;
        }

        return new Message($type, $data);
    }

    /**
     * @param array<string, mixed>|null $output
     */
    private function spanMessage(string $id, MessageType $type, ?array $output = null): Message
    {
        $data = ['id' => $id, 'trace_id' => 'trace-1', 'name' => 'test', 'type' => 'general', 'project_name' => 'test', 'start_time' => '2024-01-01T00:00:00Z'];
        if ($output !== null) {
            $data['output'] = $output;
        }

        return new Message($type, $data);
    }

    #[Test]
    public function shouldDeduplicateTraceMessagesKeepingLatest(): void
    {
        $this->batchQueue->enqueue($this->traceMessage('trace-1', MessageType::CREATE_TRACE, output: null));
        $this->batchQueue->enqueue($this->traceMessage('trace-1', MessageType::UPDATE_TRACE, output: ['result' => 'done']));
        $this->batchQueue->flush();

        self::assertCount(1, $this->capturedData);
        self::assertSame(['result' => 'done'], $this->capturedData[0]['output']);
    }

    #[Test]
    public function shouldDeduplicateSpanMessagesKeepingLatest(): void
    {
        $this->batchQueue->enqueue($this->spanMessage('span-1', MessageType::CREATE_SPAN, output: null));
        $this->batchQueue->enqueue($this->spanMessage('span-1', MessageType::UPDATE_SPAN, output: ['result' => 'done']));
        $this->batchQueue->flush();

        self::assertCount(1, $this->capturedData);
        self::assertSame(['result' => 'done'], $this->capturedData[0]['output']);
    }

    #[Test]
    public function shouldKeepTracesWithDifferentIds(): void
    {
        $this->batchQueue->enqueue($this->traceMessage('trace-1', MessageType::CREATE_TRACE));
        $this->batchQueue->enqueue($this->traceMessage('trace-2', MessageType::CREATE_TRACE));
        $this->batchQueue->flush();

        self::assertCount(2, $this->capturedData);
    }

    #[Test]
    public function shouldPreserveFinalOutputAfterMultipleUpdates(): void
    {
        $this->batchQueue->enqueue($this->traceMessage('trace-1', MessageType::CREATE_TRACE, output: null));
        $this->batchQueue->enqueue($this->traceMessage('trace-1', MessageType::UPDATE_TRACE, output: ['v' => 1]));
        $this->batchQueue->enqueue($this->traceMessage('trace-1', MessageType::UPDATE_TRACE, output: ['v' => 2]));
        $this->batchQueue->enqueue($this->traceMessage('trace-1', MessageType::UPDATE_TRACE, output: ['v' => 'final']));
        $this->batchQueue->flush();

        self::assertCount(1, $this->capturedData);
        self::assertSame(['v' => 'final'], $this->capturedData[0]['output']);
    }

    #[Test]
    public function shouldFlushWhenBatchCountLimitReached(): void
    {
        // Add messages up to the limit (25)
        for ($i = 1; $i <= Config::MAX_BATCH_COUNT; $i++) {
            $this->batchQueue->enqueue($this->traceMessage("trace-{$i}", MessageType::CREATE_TRACE));
        }

        // Should have auto-flushed when we added the 25th item
        self::assertSame(1, $this->flushCount, 'Expected flush after adding 25th item');
        self::assertCount(Config::MAX_BATCH_COUNT, $this->capturedData);

        // Add one more - should not trigger another flush since batch was already flushed
        $this->batchQueue->enqueue($this->traceMessage('trace-26', MessageType::CREATE_TRACE));
        self::assertSame(1, $this->flushCount, 'Should not flush again after adding 26th item to empty batch');

        // Flush remaining (26th message)
        $this->batchQueue->flush();
        self::assertSame(2, $this->flushCount);
        self::assertCount(1, $this->capturedData); // Only the 26th message
    }

    #[Test]
    public function shouldFlushWhenBatchSizeLimitReached(): void
    {
        // Create a large message that exceeds size limit
        $largeData = str_repeat('x', Config::MAX_BATCH_SIZE_BYTES + 1000);
        $largeMessage = new Message(MessageType::CREATE_TRACE, [
            'id' => 'large-trace',
            'name' => 'large-trace',
            'project_name' => 'test',
            'start_time' => '2024-01-01T00:00:00Z',
            'large_data' => $largeData,
        ]);

        // Add a small message first
        $this->batchQueue->enqueue($this->traceMessage('trace-1', MessageType::CREATE_TRACE));
        self::assertSame(0, $this->flushCount);

        // Add large message - should trigger flush of first message, then add large message
        $this->batchQueue->enqueue($largeMessage);

        self::assertSame(1, $this->flushCount);
        self::assertCount(1, $this->capturedData); // Should contain first message

        // Flush remaining (large message)
        $this->batchQueue->flush();
        self::assertSame(2, $this->flushCount);
        self::assertCount(1, $this->capturedData); // Should contain large message
    }

    #[Test]
    public function shouldNotFlushEmptyBatch(): void
    {
        // Empty queue should not trigger flush
        $this->batchQueue->flush();
        self::assertSame(0, $this->flushCount);
        self::assertCount(0, $this->capturedData);
    }

    #[Test]
    public function shouldMixTracesAndSpansInCountLimit(): void
    {
        // Add 15 traces and 10 spans (25 total) - should trigger flush after 25th item
        for ($i = 1; $i <= 15; $i++) {
            $this->batchQueue->enqueue($this->traceMessage("trace-{$i}", MessageType::CREATE_TRACE));
        }

        // No flush yet - only 15 items
        self::assertSame(0, $this->flushCount);

        for ($i = 1; $i <= 10; $i++) {
            $this->batchQueue->enqueue($this->spanMessage("span-{$i}", MessageType::CREATE_SPAN));
        }

        // Should have flushed after adding the 25th item (10th span)
        // This results in 2 HTTP calls: one for traces, one for spans
        self::assertSame(2, $this->flushCount);

        // Should have flushed spans last (10 items) - they overwrite capturedData
        self::assertCount(10, $this->capturedData);
    }

    #[Test]
    public function shouldFlushWhenTimeIntervalReached(): void
    {
        // Create a BatchQueue with a very short flush interval for testing
        $httpClient = $this->createMock(HttpClientInterface::class);
        $flushCount = 0;
        $httpClient->method('post')->willReturnCallback(function () use (&$flushCount) {
            $flushCount++;

            return [];
        });

        // We can't easily modify Config::FLUSH_INTERVAL_MS in tests, so we'll test
        // by manually manipulating the lastFlushTime via reflection
        $batchQueue = new BatchQueue($httpClient);

        // Add a message (shouldn't flush yet)
        $batchQueue->enqueue($this->traceMessage('trace-1', MessageType::CREATE_TRACE));
        self::assertSame(0, $flushCount);

        // Use reflection to set lastFlushTime to 11 seconds ago (older than 10 second limit)
        $reflection = new ReflectionClass($batchQueue);
        $lastFlushTimeProperty = $reflection->getProperty('lastFlushTime');
        $lastFlushTimeProperty->setValue($batchQueue, microtime(true) - 11.0);

        // Add another message - this should trigger time-based flush
        $batchQueue->enqueue($this->traceMessage('trace-2', MessageType::CREATE_TRACE));

        // Should have flushed due to time limit
        self::assertSame(1, $flushCount);
    }
}
