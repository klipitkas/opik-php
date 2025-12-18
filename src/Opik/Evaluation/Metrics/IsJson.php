<?php

declare(strict_types=1);

namespace Opik\Evaluation\Metrics;

/**
 * IsJson metric - checks if a given output string is valid JSON.
 */
final class IsJson extends BaseMetric
{
    public function __construct(string $name = 'is_json')
    {
        parent::__construct($name);
    }

    /**
     * Calculates a score based on whether output is valid JSON.
     *
     * @param array<string, mixed> $input Must contain 'output' key
     *
     * @return ScoreResult Score result (1.0 if valid JSON, 0.0 if not)
     */
    public function score(array $input): ScoreResult
    {
        $output = $input['output'] ?? null;

        if (! \is_string($output)) {
            return new ScoreResult(
                name: $this->name,
                value: 0.0,
                reason: 'IsJson: Output is not a string',
            );
        }

        json_decode($output);
        $isValid = json_last_error() === JSON_ERROR_NONE;

        return new ScoreResult(
            name: $this->name,
            value: $isValid ? 1.0 : 0.0,
            reason: $isValid
                ? 'IsJson: Output is valid JSON'
                : 'IsJson: Output is not valid JSON',
        );
    }
}
