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
 * Represents a span - a unit of work within a trace.
 *
 * Spans capture individual operations within a trace, such as LLM calls,
 * tool invocations, or general processing steps. Spans can be nested
 * to represent hierarchical operations.
 */
final class Span
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

    private ?string $model = null;

    private ?string $provider = null;

    private ?Usage $usage = null;

    private ?ErrorInfo $errorInfo = null;

    private ?float $totalCost = null;

    private bool $ended = false;

    /**
     * Create a new span.
     *
     * @param BatchQueue $batchQueue The batch queue for sending messages
     * @param string $traceId The ID of the parent trace
     * @param string $name The name of the span
     * @param string $projectName The project this span belongs to
     * @param string|null $parentSpanId Optional parent span ID for nesting
     * @param SpanType $type The type of span (General, Llm, Tool)
     * @param string|null $id Optional custom span ID (UUID v7 generated if not provided)
     * @param DateTimeImmutable|null $startTime Optional start time (current time if not provided)
     * @param mixed $input Optional input data for the span
     * @param array<string, mixed>|null $metadata Optional metadata key-value pairs
     * @param array<int, string>|null $tags Optional list of tags
     */
    public function __construct(
        private readonly BatchQueue $batchQueue,
        private readonly string $traceId,
        private readonly string $name,
        private readonly string $projectName,
        private readonly ?string $parentSpanId = null,
        private readonly SpanType $type = SpanType::GENERAL,
        ?string $id = null,
        ?DateTimeImmutable $startTime = null,
        mixed $input = null,
        ?array $metadata = null,
        ?array $tags = null,
    ) {
        $this->id = $id ?? IdGenerator::uuid();
        $this->startTime = $startTime ?? DateTimeHelper::now();
        $this->input = $input;
        $this->metadata = $metadata ?? [];
        $this->tags = $tags ?? [];

        $this->sendCreate();
    }

    /**
     * Get the span ID.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the parent trace ID.
     */
    public function getTraceId(): string
    {
        return $this->traceId;
    }

    /**
     * Get the span name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the span type.
     */
    public function getType(): SpanType
    {
        return $this->type;
    }

    /**
     * Create a child span nested under this span.
     *
     * @param string $name The name of the child span
     * @param SpanType $type The type of span
     * @param mixed $input Optional input data
     * @param array<string, mixed>|null $metadata Optional metadata
     * @param array<int, string>|null $tags Optional tags
     * @return self The created child span
     */
    public function span(
        string $name,
        SpanType $type = SpanType::GENERAL,
        mixed $input = null,
        ?array $metadata = null,
        ?array $tags = null,
    ): self {
        return new self(
            batchQueue: $this->batchQueue,
            traceId: $this->traceId,
            name: $name,
            projectName: $this->projectName,
            parentSpanId: $this->id,
            type: $type,
            input: $input,
            metadata: $metadata,
            tags: $tags,
        );
    }

    /**
     * Update the span with additional data.
     *
     * @param mixed $input New input data (replaces existing if provided)
     * @param mixed $output Output data from the operation
     * @param array<string, mixed>|null $metadata Additional metadata (merged with existing)
     * @param array<int, string>|null $tags Additional tags (merged with existing)
     * @param DateTimeImmutable|null $endTime End time of the span
     * @param string|null $model LLM model name (for LLM spans)
     * @param string|null $provider LLM provider name (for LLM spans)
     * @param Usage|null $usage Token usage statistics (for LLM spans)
     * @param ErrorInfo|null $errorInfo Error information if the operation failed
     * @param float|null $totalCost Total cost override for the span
     * @return self For method chaining
     */
    public function update(
        mixed $input = null,
        mixed $output = null,
        ?array $metadata = null,
        ?array $tags = null,
        ?DateTimeImmutable $endTime = null,
        ?string $model = null,
        ?string $provider = null,
        ?Usage $usage = null,
        ?ErrorInfo $errorInfo = null,
        ?float $totalCost = null,
    ): self {
        if ($input !== null) {
            $this->input = $input;
        }

        if ($output !== null) {
            $this->output = $output;
        }

        if ($metadata !== null) {
            $this->metadata = \array_merge($this->metadata, $metadata);
        }

        if ($tags !== null) {
            $this->tags = \array_merge($this->tags, $tags);
        }

        if ($endTime !== null) {
            $this->endTime = $endTime;
        }

        if ($model !== null) {
            $this->model = $model;
        }

        if ($provider !== null) {
            $this->provider = $provider;
        }

        if ($usage !== null) {
            $this->usage = $usage;
        }

        if ($errorInfo !== null) {
            $this->errorInfo = $errorInfo;
        }

        if ($totalCost !== null) {
            $this->totalCost = $totalCost;
        }

        $this->sendUpdate();

        return $this;
    }

    /**
     * End the span, recording its completion time.
     *
     * This method is idempotent - calling it multiple times has no effect
     * after the first call.
     *
     * @param DateTimeImmutable|null $endTime Optional end time (current time if not provided)
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
     * Log a feedback score for this span.
     *
     * @param string $name The name of the feedback metric
     * @param float|string $value Numeric score value or category name
     * @param string|null $reason Optional explanation for the score
     * @param string|null $categoryName Optional category name (for categorical scores)
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
            'span_id' => $this->id,
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
            data: $data,
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
            'trace_id' => $this->traceId,
            'name' => $this->name,
            'type' => $this->type->value,
            'project_name' => $this->projectName,
            'start_time' => DateTimeHelper::format($this->startTime),
        ];

        if ($this->parentSpanId !== null) {
            $data['parent_span_id'] = $this->parentSpanId;
        }

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

        if ($this->model !== null) {
            $data['model'] = $this->model;
        }

        if ($this->provider !== null) {
            $data['provider'] = $this->provider;
        }

        if ($this->usage !== null) {
            $data['usage'] = $this->usage->toArray();
        }

        if ($this->errorInfo !== null) {
            $data['error_info'] = $this->errorInfo->toArray();
        }

        if ($this->totalCost !== null) {
            $data['total_estimated_cost'] = $this->totalCost;
        }

        return $data;
    }

    /**
     * Send a create message for this span to the batch queue.
     */
    private function sendCreate(): void
    {
        $this->batchQueue->enqueue(new Message(
            type: MessageType::CREATE_SPAN,
            data: $this->toArray(),
        ));
    }

    /**
     * Send an update message for this span to the batch queue.
     */
    private function sendUpdate(): void
    {
        $this->batchQueue->enqueue(new Message(
            type: MessageType::UPDATE_SPAN,
            data: $this->toArray(),
        ));
    }
}
