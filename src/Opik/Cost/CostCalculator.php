<?php

declare(strict_types=1);

namespace Opik\Cost;

use Opik\Tracer\Usage;

/**
 * Calculator for LLM usage costs.
 *
 * Allows users to calculate costs based on their own pricing.
 * Prices are specified in cost per token (not per 1K or 1M tokens).
 */
final class CostCalculator
{
    /**
     * Calculate the total cost for a given usage.
     *
     * @param Usage $usage Token usage from LLM call
     * @param float $inputCostPerToken Cost per input/prompt token (e.g., 0.0000025 for $2.50/1M tokens)
     * @param float $outputCostPerToken Cost per output/completion token (e.g., 0.00001 for $10/1M tokens)
     */
    public static function calculate(
        Usage $usage,
        float $inputCostPerToken,
        float $outputCostPerToken,
    ): float {
        $inputCost = ($usage->promptTokens ?? 0) * $inputCostPerToken;
        $outputCost = ($usage->completionTokens ?? 0) * $outputCostPerToken;

        return $inputCost + $outputCost;
    }

    /**
     * Calculate the total cost using per-million token pricing.
     *
     * This is a convenience method for the common pricing format ($/1M tokens).
     *
     * @param Usage $usage Token usage from LLM call
     * @param float $inputCostPerMillion Cost per 1M input tokens (e.g., 2.50 for $2.50/1M)
     * @param float $outputCostPerMillion Cost per 1M output tokens (e.g., 10.00 for $10/1M)
     */
    public static function calculateFromMillionPricing(
        Usage $usage,
        float $inputCostPerMillion,
        float $outputCostPerMillion,
    ): float {
        return self::calculate(
            $usage,
            $inputCostPerMillion / 1_000_000,
            $outputCostPerMillion / 1_000_000,
        );
    }
}
