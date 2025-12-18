<?php

declare(strict_types=1);

namespace Opik\Message;

use Opik\Api\HttpClientInterface;
use Opik\Config\Config;
use Opik\Utils\JsonEncoder;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

final class BatchQueue
{
    private static bool $globalShutdownRegistered = false;

    /** @var array<int, self> */
    private static array $instances = [];

    /** @var array<string, Message> */
    private array $traceMessages = [];

    /** @var array<string, Message> */
    private array $spanMessages = [];

    /** @var array<int, Message> */
    private array $feedbackScoreMessages = [];

    private int $currentBatchSize = 0;

    private float $lastFlushTime;

    private readonly LoggerInterface $logger;

    /** @var callable(array<string, mixed>, Throwable): void|null */
    private $onFlushFailure = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->lastFlushTime = microtime(true);
        self::$instances[spl_object_id($this)] = $this;
        self::registerGlobalShutdown();
    }

    /**
     * Set a callback to be invoked when a flush operation fails.
     *
     * The callback receives the failed data and the exception.
     * This allows users to implement custom error handling (e.g., save to file, send to Sentry).
     *
     * @param callable(array<string, mixed>, Throwable): void $callback
     *
     * @example
     * ```php
     * $client->getBatchQueue()->onFlushFailure(function(array $data, Throwable $e) {
     *     error_log("Failed to flush: " . $e->getMessage());
     *     file_put_contents('failed_data.json', json_encode($data), FILE_APPEND);
     * });
     * ```
     */
    public function onFlushFailure(callable $callback): void
    {
        $this->onFlushFailure = $callback;
    }

    public function enqueue(Message $message): void
    {
        $messageSize = \strlen(JsonEncoder::encode($message->data));

        // Check batch size limit
        if ($this->currentBatchSize + $messageSize > Config::MAX_BATCH_SIZE_BYTES) {
            $this->logger->debug('Batch size limit reached, flushing', [
                'current_size' => $this->currentBatchSize,
                'message_size' => $messageSize,
                'limit' => Config::MAX_BATCH_SIZE_BYTES,
            ]);
            $this->flush();
        }

        // Check time-based flush
        $now = microtime(true);
        if (($now - $this->lastFlushTime) * 1000 >= Config::FLUSH_INTERVAL_MS) {
            $this->logger->debug('Flush interval reached, flushing', [
                'time_since_last_flush_ms' => ($now - $this->lastFlushTime) * 1000,
                'interval_ms' => Config::FLUSH_INTERVAL_MS,
            ]);
            $this->flush();
        }

        $this->logger->debug('Enqueueing message', [
            'type' => $message->type->name,
            'id' => $message->data['id'] ?? null,
            'name' => $message->data['name'] ?? null,
            'trace_id' => $message->data['trace_id'] ?? null,
        ]);

        // For traces and spans, use ID as key to keep only the latest version
        // This prevents duplicate entries when update() is called after creation
        match ($message->type) {
            MessageType::CREATE_TRACE, MessageType::UPDATE_TRACE => $this->traceMessages[$message->data['id']] = $message,
            MessageType::CREATE_SPAN, MessageType::UPDATE_SPAN => $this->spanMessages[$message->data['id']] = $message,
            MessageType::ADD_FEEDBACK_SCORE => $this->feedbackScoreMessages[] = $message,
        };

        $this->currentBatchSize += $messageSize;

        // Check batch count limit - flush when we have reached the max count
        $messagesBatchCount = \count($this->traceMessages) + \count($this->spanMessages) + \count($this->feedbackScoreMessages);
        if ($messagesBatchCount >= Config::MAX_BATCH_COUNT) {
            $this->logger->debug('Batch count limit reached, flushing', [
                'current_count' => $messagesBatchCount,
                'limit' => Config::MAX_BATCH_COUNT,
            ]);
            $this->flush();
        }
    }

    public function flush(): void
    {
        $this->flushTraces();
        $this->flushSpans();
        $this->flushFeedbackScores();
        $this->currentBatchSize = 0;
        $this->lastFlushTime = microtime(true);
    }

    public function isEmpty(): bool
    {
        return $this->traceMessages === []
            && $this->spanMessages === []
            && $this->feedbackScoreMessages === [];
    }

    private function flushTraces(): void
    {
        if ($this->traceMessages === []) {
            return;
        }

        $traces = array_map(
            static fn (Message $m) => $m->data,
            array_values($this->traceMessages),
        );

        try {
            $this->httpClient->post('v1/private/traces/batch', ['traces' => $traces]);
            $this->logger->debug('Flushed traces', ['count' => \count($traces)]);
        } catch (Throwable $e) {
            $this->logger->error('Failed to flush traces', [
                'count' => \count($traces),
                'error' => $e->getMessage(),
            ]);
            $this->invokeFlushFailureCallback(['traces' => $traces], $e);
        }

        $this->traceMessages = [];
    }

    private function flushSpans(): void
    {
        if ($this->spanMessages === []) {
            return;
        }

        $spans = array_map(
            static fn (Message $m) => $m->data,
            array_values($this->spanMessages),
        );

        try {
            $this->httpClient->post('v1/private/spans/batch', ['spans' => $spans]);
            $this->logger->debug('Flushed spans', ['count' => \count($spans)]);
        } catch (Throwable $e) {
            $this->logger->error('Failed to flush spans', [
                'count' => \count($spans),
                'error' => $e->getMessage(),
            ]);
            $this->invokeFlushFailureCallback(['spans' => $spans], $e);
        }

        $this->spanMessages = [];
    }

    private function flushFeedbackScores(): void
    {
        if ($this->feedbackScoreMessages === []) {
            return;
        }

        $scores = array_map(
            static fn (Message $m) => $m->data,
            $this->feedbackScoreMessages,
        );

        try {
            $this->httpClient->put('v1/private/spans/feedback-scores', ['scores' => $scores]);
            $this->logger->debug('Flushed feedback scores', ['count' => \count($scores)]);
        } catch (Throwable $e) {
            $this->logger->error('Failed to flush feedback scores', [
                'count' => \count($scores),
                'error' => $e->getMessage(),
            ]);
            $this->invokeFlushFailureCallback(['feedback_scores' => $scores], $e);
        }

        $this->feedbackScoreMessages = [];
    }

    /**
     * Invoke the flush failure callback if set.
     *
     * @param array<string, mixed> $data The data that failed to flush
     * @param Throwable $exception The exception that caused the failure
     */
    private function invokeFlushFailureCallback(array $data, Throwable $exception): void
    {
        if ($this->onFlushFailure !== null) {
            try {
                ($this->onFlushFailure)($data, $exception);
            } catch (Throwable $e) {
                $this->logger->error('Flush failure callback threw an exception', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private static function registerGlobalShutdown(): void
    {
        if (self::$globalShutdownRegistered) {
            return;
        }

        self::$globalShutdownRegistered = true;

        register_shutdown_function(static function (): void {
            foreach (self::$instances as $instance) {
                if (!$instance->isEmpty()) {
                    $instance->flush();
                }
            }
        });
    }

    public function __destruct()
    {
        unset(self::$instances[spl_object_id($this)]);
    }
}
