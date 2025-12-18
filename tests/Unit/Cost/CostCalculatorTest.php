<?php

declare(strict_types=1);

namespace Opik\Tests\Unit\Cost;

use Opik\Cost\CostCalculator;
use Opik\Tracer\Usage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CostCalculatorTest extends TestCase
{
    #[Test]
    public function shouldCalculateCostFromPerTokenPricing(): void
    {
        $usage = new Usage(
            promptTokens: 1000,
            completionTokens: 500,
        );

        // GPT-4o pricing: $2.50/1M input, $10/1M output
        $cost = CostCalculator::calculate(
            $usage,
            inputCostPerToken: 0.0000025,
            outputCostPerToken: 0.00001,
        );

        // 1000 * 0.0000025 + 500 * 0.00001 = 0.0025 + 0.005 = 0.0075
        self::assertEqualsWithDelta(0.0075, $cost, 0.0000001);
    }

    #[Test]
    public function shouldCalculateCostFromMillionPricing(): void
    {
        $usage = new Usage(
            promptTokens: 1000,
            completionTokens: 500,
        );

        // GPT-4o pricing: $2.50/1M input, $10/1M output
        $cost = CostCalculator::calculateFromMillionPricing(
            $usage,
            inputCostPerMillion: 2.50,
            outputCostPerMillion: 10.00,
        );

        // 1000 * (2.50/1M) + 500 * (10/1M) = 0.0025 + 0.005 = 0.0075
        self::assertEqualsWithDelta(0.0075, $cost, 0.0000001);
    }

    #[Test]
    public function shouldHandleZeroTokens(): void
    {
        $usage = new Usage(
            promptTokens: 0,
            completionTokens: 0,
        );

        $cost = CostCalculator::calculate(
            $usage,
            inputCostPerToken: 0.0000025,
            outputCostPerToken: 0.00001,
        );

        self::assertSame(0.0, $cost);
    }

    #[Test]
    public function shouldHandleNullTokens(): void
    {
        $usage = new Usage();

        $cost = CostCalculator::calculate(
            $usage,
            inputCostPerToken: 0.0000025,
            outputCostPerToken: 0.00001,
        );

        self::assertSame(0.0, $cost);
    }

    #[Test]
    public function shouldCalculateInputOnlyCost(): void
    {
        $usage = new Usage(
            promptTokens: 10000,
            completionTokens: 0,
        );

        $cost = CostCalculator::calculateFromMillionPricing(
            $usage,
            inputCostPerMillion: 3.00,
            outputCostPerMillion: 15.00,
        );

        // 10000 * (3/1M) = 0.03
        self::assertEqualsWithDelta(0.03, $cost, 0.0000001);
    }

    #[Test]
    public function shouldCalculateOutputOnlyCost(): void
    {
        $usage = new Usage(
            promptTokens: 0,
            completionTokens: 5000,
        );

        $cost = CostCalculator::calculateFromMillionPricing(
            $usage,
            inputCostPerMillion: 3.00,
            outputCostPerMillion: 15.00,
        );

        // 5000 * (15/1M) = 0.075
        self::assertEqualsWithDelta(0.075, $cost, 0.0000001);
    }

    #[Test]
    public function shouldCalculateLargeUsageCost(): void
    {
        $usage = new Usage(
            promptTokens: 100000,
            completionTokens: 50000,
        );

        // Claude 3.5 Sonnet pricing: $3/1M input, $15/1M output
        $cost = CostCalculator::calculateFromMillionPricing(
            $usage,
            inputCostPerMillion: 3.00,
            outputCostPerMillion: 15.00,
        );

        // 100000 * (3/1M) + 50000 * (15/1M) = 0.30 + 0.75 = 1.05
        self::assertEqualsWithDelta(1.05, $cost, 0.0000001);
    }
}
