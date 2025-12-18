<?php

declare(strict_types=1);

namespace Opik\Tests\Unit\Evaluation\Metrics;

use Opik\Evaluation\Metrics\IsJson;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IsJsonTest extends TestCase
{
    #[Test]
    public function shouldReturnOneForValidJson(): void
    {
        $metric = new IsJson();

        $result = $metric->score([
            'output' => '{"name": "test", "value": 123}',
        ]);

        self::assertSame('is_json', $result->name);
        self::assertSame(1.0, $result->value);
        self::assertStringContainsString('valid JSON', $result->reason);
    }

    #[Test]
    public function shouldReturnZeroForInvalidJson(): void
    {
        $metric = new IsJson();

        $result = $metric->score([
            'output' => 'not valid json',
        ]);

        self::assertSame(0.0, $result->value);
        self::assertStringContainsString('not valid JSON', $result->reason);
    }

    #[Test]
    public function shouldValidateJsonArray(): void
    {
        $metric = new IsJson();

        $result = $metric->score([
            'output' => '[1, 2, 3]',
        ]);

        self::assertSame(1.0, $result->value);
    }

    #[Test]
    public function shouldValidateJsonString(): void
    {
        $metric = new IsJson();

        $result = $metric->score([
            'output' => '"hello"',
        ]);

        self::assertSame(1.0, $result->value);
    }

    #[Test]
    public function shouldValidateJsonNumber(): void
    {
        $metric = new IsJson();

        $result = $metric->score([
            'output' => '123',
        ]);

        self::assertSame(1.0, $result->value);
    }

    #[Test]
    public function shouldValidateJsonBoolean(): void
    {
        $metric = new IsJson();

        $result = $metric->score([
            'output' => 'true',
        ]);

        self::assertSame(1.0, $result->value);
    }

    #[Test]
    public function shouldValidateJsonNull(): void
    {
        $metric = new IsJson();

        $result = $metric->score([
            'output' => 'null',
        ]);

        self::assertSame(1.0, $result->value);
    }

    #[Test]
    public function shouldReturnZeroForNonString(): void
    {
        $metric = new IsJson();

        $result = $metric->score([
            'output' => 123,
        ]);

        self::assertSame(0.0, $result->value);
        self::assertStringContainsString('not a string', $result->reason);
    }

    #[Test]
    public function shouldUseCustomName(): void
    {
        $metric = new IsJson('json_validator');

        $result = $metric->score([
            'output' => '{}',
        ]);

        self::assertSame('json_validator', $result->name);
    }

    #[Test]
    public function shouldRejectMalformedJson(): void
    {
        $metric = new IsJson();

        $result = $metric->score([
            'output' => '{"key": "value",}', // trailing comma
        ]);

        self::assertSame(0.0, $result->value);
    }

    #[Test]
    public function shouldRejectUnquotedKeys(): void
    {
        $metric = new IsJson();

        $result = $metric->score([
            'output' => '{key: "value"}',
        ]);

        self::assertSame(0.0, $result->value);
    }
}
