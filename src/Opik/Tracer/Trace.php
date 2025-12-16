<?php

declare(strict_types=1);

namespace Opik\Tracer;

use DateTimeImmutable;
use Opik\Message\BatchQueue;
use Opik\Message\Message;
use Opik\Message\MessageType;
use Opik\Utils\DateTimeHelper;
use Opik\Utils\IdGenerator;

/**
 * Represents a trace - the top-level unit of work in the Opik tracing system.
 *
 * A trace captures the complete execution of an operation, including timing,
 * inputs, outputs, and any nested spans representing sub-operations.
 */
final class Trace
{
    private readonly string $id;

    private readonly DateTimeImmutable $startTime;

    private ?DateTimeImmutable $endTime = null;

    private mixed $input = null;

    private mixed $output = null;

    /** @var array<string, mixed> */
    private array $metadata = [];

    /** @var array<int, string> */
    private array $tags = [];

    private ?ErrorInfo $errorInfo = null;

    private ?string $threadId = null;

    private bool $ended = false;

    /**
     * Create a new trace.
     *
     * @param BatchQueue $batchQueue The batch queue for sending messages
     * @param string $name The name of the trace
     * @param string $projectName The project this trace belongs to
     * @param string|null $id Optional custom trace ID (UUID v7 generated if not provided)
     * @param DateTimeImmutable|null $startTime Optional start time (current time if not provided)
     * @param mixed $input Optional input data for the trace
     * @param array<string, mixed>|null $metadata Optional metadata key-value pairs
     * @param array<int, string>|null $tags Optional list of tags
     * @param string|null $threadId Optional thread ID for grouping related traces
     */
    public function __construct(
        private readonly BatchQueue $batchQueue,
        private readonly string $name,
        private readonly string $projectName,
        ?string $id = null,
        ?DateTimeImmutable $startTime = null,
        mixed $input = null,
        ?array $metadata = null,
        ?array $tags = null,
        ?string $threadId = null,
    ) {
        $this->id = $id ?? IdGenerator::uuid();
        $this->startTime = $startTime ?? DateTimeHelper::now();
        $this->input = $input;
        $this->metadata = $metadata ?? [];
        $this->tags = $tags ?? [];
        $this->threadId = $threadId;

        $this->sendCreate();
    }

    /**
     * Get the trace ID.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the trace name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the project name.
     */
    public function getProjectName(): string
    {
        return $this->projectName;
    }

    /**
     * Get the thread ID.
     */
    public function getThreadId(): ?string
    {
        return $this->threadId;
    }

    /**
     * Create a new child span within this trace.
     *
     * @param string $name The name of the span
     * @param string|null $parentSpanId Optional parent span ID for nesting
     * @param SpanType $type The type of span (General, Llm, Tool)
     * @param mixed $input Optional input data for the span
     * @param array<string, mixed>|null $metadata Optional metadata
     * @param array<int, string>|null $tags Optional tags
     *
     * @return Span The created span
     */
    public function span(
        string $name,
        ?string $parentSpanId = null,
        SpanType $type = SpanType::GENERAL,
        mixed $input = null,
        ?array $metadata = null,
        ?array $tags = null,
    ): Span {
        return new Span(
            batchQueue: $this->batchQueue,
            traceId: $this->id,
            name: $name,
            projectName: $this->projectName,
            parentSpanId: $parentSpanId,
            type: $type,
            input: $input,
            metadata: $metadata,
            tags: $tags,
        );
    }

    /**
     * Update the trace with additional data.
     *
     * @param mixed $input New input data (replaces existing if provided)
     * @param mixed $output Output data from the operation
     * @param array<string, mixed>|null $metadata Additional metadata (merged with existing)
     * @param array<int, string>|null $tags Additional tags (merged with existing)
     * @param DateTimeImmutable|null $endTime End time of the trace
     * @param ErrorInfo|null $errorInfo Error information if the operation failed
     *
     * @return self For method chaining
     */
    public function update(
        mixed $input = null,
        mixed $output = null,
        ?array $metadata = null,
        ?array $tags = null,
        ?DateTimeImmutable $endTime = null,
        ?ErrorInfo $errorInfo = null,
    ): self {
        if ($input !== null) {
            $this->input = $input;
        }

        if ($output !== null) {
            $this->output = $output;
        }

        if ($metadata !== null) {
            $this->metadata = array_merge($this->metadata, $metadata);
        }

        if ($tags !== null) {
            $this->tags = array_merge($this->tags, $tags);
        }

        if ($endTime !== null) {
            $this->endTime = $endTime;
        }

        if ($errorInfo !== null) {
            $this->errorInfo = $errorInfo;
        }

        $this->sendUpdate();

        return $this;
    }

    /**
     * End the trace, recording its completion time.
     *
     * This method is idempotent - calling it multiple times has no effect
     * after the first call.
     *
     * @param DateTimeImmutable|null $endTime Optional end time (current time if not provided)
     *
     * @return self For method chaining
     */
    public function end(?DateTimeImmutable $endTime = null): self
    {
        if ($this->ended) {
            return $this;
        }

        $this->endTime = $endTime ?? DateTimeHelper::now();
        $this->ended = true;
        $this->sendUpdate();

        return $this;
    }

    /**
     * Log a feedback score for this trace.
     *
     * @param string $name The name of the feedback metric
     * @param float|string $value Numeric score value or category name
     * @param string|null $reason Optional explanation for the score
     * @param string|null $categoryName Optional category name (for categorical scores)
     *
     * @return self For method chaining
     */
    public function logFeedbackScore(
        string $name,
        float|string $value,
        ?string $reason = null,
        ?string $categoryName = null,
    ): self {
        $data = [
            'id' => IdGenerator::uuid(),
            'name' => $name,
            'source' => 'sdk',
            'project_name' => $this->projectName,
        ];

        if (\is_float($value)) {
            $data['value'] = $value;
        } else {
            $data['category_name'] = $categoryName ?? $value;
        }

        if ($reason !== null) {
            $data['reason'] = $reason;
        }

        $this->batchQueue->enqueue(new Message(
            type: MessageType::ADD_FEEDBACK_SCORE,
            data: array_merge($data, ['trace_id' => $this->id]),
        ));

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'project_name' => $this->projectName,
            'start_time' => DateTimeHelper::format($this->startTime),
        ];

        if ($this->endTime !== null) {
            $data['end_time'] = DateTimeHelper::format($this->endTime);
        }

        if ($this->input !== null) {
            $data['input'] = $this->input;
        }

        if ($this->output !== null) {
            $data['output'] = $this->output;
        }

        if ($this->metadata !== []) {
            $data['metadata'] = $this->metadata;
        }

        if ($this->tags !== []) {
            $data['tags'] = $this->tags;
        }

        if ($this->threadId !== null) {
            $data['thread_id'] = $this->threadId;
        }

        if ($this->errorInfo !== null) {
            $data['error_info'] = $this->errorInfo->toArray();
        }

        return $data;
    }

    /**
     * Send a create message for this trace to the batch queue.
     */
    private function sendCreate(): void
    {
        $this->batchQueue->enqueue(new Message(
            type: MessageType::CREATE_TRACE,
            data: $this->toArray(),
        ));
    }

    /**
     * Send an update message for this trace to the batch queue.
     */
    private function sendUpdate(): void
    {
        $this->batchQueue->enqueue(new Message(
            type: MessageType::UPDATE_TRACE,
            data: $this->toArray(),
        ));
    }
}
