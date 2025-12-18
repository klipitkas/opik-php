<?php

declare(strict_types=1);

namespace Opik\Evaluation\Metrics;

/**
 * RegexMatch metric - checks if the actual output matches a regex pattern.
 */
final class RegexMatch extends BaseMetric
{
    public function __construct(string $name = 'regex_match')
    {
        parent::__construct($name);
    }

    /**
     * Calculates a score based on regex match.
     *
     * @param array<string, mixed> $input Must contain 'output' and 'pattern' keys
     *
     * @return ScoreResult Score result (1.0 for match, 0.0 for no match)
     */
    public function score(array $input): ScoreResult
    {
        $output = (string) ($input['output'] ?? '');
        $pattern = (string) ($input['pattern'] ?? '');

        // Ensure pattern has delimiters
        if (! str_starts_with($pattern, '/') && ! str_starts_with($pattern, '#')) {
            $pattern = '/' . $pattern . '/';
        }

        $isMatch = @preg_match($pattern, $output) === 1;

        return new ScoreResult(
            name: $this->name,
            value: $isMatch ? 1.0 : 0.0,
            reason: $isMatch
                ? 'Regex: Output matches the pattern'
                : 'Regex: Output does not match the pattern',
        );
    }
}
