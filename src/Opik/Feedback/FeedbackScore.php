<?php

declare(strict_types=1);

namespace Opik\Feedback;

use InvalidArgumentException;
use Opik\Utils\IdGenerator;

/**
 * Represents a feedback score for traces, spans, or threads.
 *
 * Feedback scores can be either numeric (float value) or categorical (string value).
 * Use this class to create feedback scores that can be logged via OpikClient methods.
 *
 * @example
 * ```php
 * // Numeric score
 * $score = new FeedbackScore(
 *     name: 'accuracy',
 *     value: 0.95,
 *     traceId: 'trace-123',
 *     reason: 'High accuracy response'
 * );
 *
 * // Categorical score
 * $score = new FeedbackScore(
 *     name: 'sentiment',
 *     categoryName: 'positive',
 *     spanId: 'span-456'
 * );
 * ```
 */
final class FeedbackScore
{
    public readonly string $id;

    public readonly string $name;

    public readonly ?float $value;

    public readonly ?string $categoryName;

    public readonly ?string $reason;

    public readonly FeedbackScoreSource $source;

    public readonly ?string $traceId;

    public readonly ?string $spanId;

    public readonly ?string $threadId;

    /**
     * Create a new feedback score.
     *
     * @param string $name The name/type of the feedback score
     * @param float|null $value Numeric value (0.0-1.0 recommended)
     * @param string|null $categoryName Categorical value (alternative to numeric value)
     * @param string|null $reason Explanation for the score
     * @param string|null $traceId Associated trace ID
     * @param string|null $spanId Associated span ID
     * @param string|null $threadId Associated thread ID
     * @param FeedbackScoreSource $source Source of the score
     * @param string|null $id Custom ID (generated if not provided)
     *
     * @throws InvalidArgumentException If name is empty or neither value nor categoryName is provided
     */
    public function __construct(
        string $name,
        ?float $value = null,
        ?string $categoryName = null,
        ?string $reason = null,
        ?string $traceId = null,
        ?string $spanId = null,
        ?string $threadId = null,
        FeedbackScoreSource $source = FeedbackScoreSource::SDK,
        ?string $id = null,
    ) {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Feedback score name cannot be empty');
        }

        if ($value === null && $categoryName === null) {
            throw new InvalidArgumentException('Either value or categoryName must be provided');
        }

        $this->id = $id ?? IdGenerator::uuid();
        $this->name = $name;
        $this->value = $value;
        $this->categoryName = $categoryName;
        $this->reason = $reason;
        $this->traceId = $traceId;
        $this->spanId = $spanId;
        $this->threadId = $threadId;
        $this->source = $source;
    }

    /**
     * Create a feedback score for a trace.
     */
    public static function forTrace(
        string $traceId,
        string $name,
        ?float $value = null,
        ?string $categoryName = null,
        ?string $reason = null,
    ): self {
        return new self(
            name: $name,
            value: $value,
            categoryName: $categoryName,
            reason: $reason,
            traceId: $traceId,
        );
    }

    /**
     * Create a feedback score for a span.
     */
    public static function forSpan(
        string $spanId,
        string $name,
        ?float $value = null,
        ?string $categoryName = null,
        ?string $reason = null,
    ): self {
        return new self(
            name: $name,
            value: $value,
            categoryName: $categoryName,
            reason: $reason,
            spanId: $spanId,
        );
    }

    /**
     * Create a feedback score for a thread.
     */
    public static function forThread(
        string $threadId,
        string $name,
        ?float $value = null,
        ?string $categoryName = null,
        ?string $reason = null,
    ): self {
        return new self(
            name: $name,
            value: $value,
            categoryName: $categoryName,
            reason: $reason,
            threadId: $threadId,
        );
    }

    /**
     * Create from array (e.g., from API response).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            value: $data['value'] ?? null,
            categoryName: $data['category_name'] ?? null,
            reason: $data['reason'] ?? null,
            traceId: $data['trace_id'] ?? null,
            spanId: $data['span_id'] ?? null,
            threadId: $data['thread_id'] ?? null,
            source: isset($data['source']) ? FeedbackScoreSource::from($data['source']) : FeedbackScoreSource::SDK,
            id: $data['id'] ?? null,
        );
    }

    /**
     * Convert to array for trace/span feedback score API requests.
     *
     * Note: The API uses 'id' to reference the trace/span the score belongs to.
     *
     * @return array<string, mixed>
     */
    public function toArray(string $projectName): array
    {
        // For trace/span feedback scores, the 'id' field is the trace/span ID
        $entityId = $this->traceId ?? $this->spanId ?? $this->id;

        $data = [
            'id' => $entityId,
            'name' => $this->name,
            'source' => $this->source->value,
            'project_name' => $projectName,
        ];

        if ($this->value !== null) {
            $data['value'] = $this->value;
        }

        if ($this->categoryName !== null) {
            $data['category_name'] = $this->categoryName;
        }

        if ($this->reason !== null) {
            $data['reason'] = $this->reason;
        }

        return $data;
    }

    /**
     * Convert to array for thread feedback score API requests.
     *
     * Note: Thread feedback scores use 'thread_id' instead of 'id'.
     *
     * @return array<string, mixed>
     */
    public function toThreadArray(string $projectName): array
    {
        $data = [
            'thread_id' => $this->threadId,
            'name' => $this->name,
            'source' => $this->source->value,
            'project_name' => $projectName,
        ];

        if ($this->value !== null) {
            $data['value'] = $this->value;
        }

        if ($this->categoryName !== null) {
            $data['category_name'] = $this->categoryName;
        }

        if ($this->reason !== null) {
            $data['reason'] = $this->reason;
        }

        return $data;
    }
}
