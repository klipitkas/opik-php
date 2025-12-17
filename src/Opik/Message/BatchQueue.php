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

    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        Config $config,
        ?LoggerInterface $logger = null,
    ) {
        unset($config);
        $this->logger = $logger ?? new NullLogger();
        self::$instances[spl_object_id($this)] = $this;
        self::registerGlobalShutdown();
    }

    public function enqueue(Message $message): void
    {
        $messageSize = \strlen(JsonEncoder::encode($message->data));

        if ($this->currentBatchSize + $messageSize > Config::MAX_BATCH_SIZE_BYTES) {
            $this->logger->debug('Batch size limit reached, flushing', [
                'current_size' => $this->currentBatchSize,
                'message_size' => $messageSize,
                'limit' => Config::MAX_BATCH_SIZE_BYTES,
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
    }

    public function flush(): void
    {
        $this->flushTraces();
        $this->flushSpans();
        $this->flushFeedbackScores();
        $this->currentBatchSize = 0;
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
        }

        $this->feedbackScoreMessages = [];
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
