<?php

declare(strict_types=1);

namespace Opik\Evaluation\Metrics;

/**
 * Represents the result of a metric evaluation.
 */
final class ScoreResult
{
    public function __construct(
        public readonly string $name,
        public readonly float $value,
        public readonly ?string $reason = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'value' => $this->value,
        ];

        if ($this->reason !== null) {
            $data['reason'] = $this->reason;
        }

        return $data;
    }
}
