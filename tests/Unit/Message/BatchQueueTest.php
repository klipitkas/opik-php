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

final class BatchQueueTest extends TestCase
{
    private HttpClientInterface $httpClient;

    private BatchQueue $batchQueue;

    /** @var array<int, array<string, mixed>> */
    private array $capturedData = [];

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->httpClient->method('post')->willReturnCallback(function ($endpoint, $data) {
            $this->capturedData = $data['traces'] ?? $data['spans'] ?? [];

            return [];
        });

        $config = new Config(baseUrl: 'http://localhost:5173/api/');
        $this->batchQueue = new BatchQueue($this->httpClient, $config);
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
}
