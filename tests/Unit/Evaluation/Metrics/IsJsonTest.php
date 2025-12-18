<?php

declare(strict_types=1);

namespace Opik\Tests\Unit\Evaluation\Metrics;

use Opik\Evaluation\Metrics\IsJson;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IsJsonTest extends TestCase
{
    #[Test]
    #[DataProvider('validJsonCases')]
    public function shouldReturnOneForValidJson(string $output): void
    {
        $result = (new IsJson())->score(['output' => $output]);

        self::assertSame(1.0, $result->value);
    }

    /** @return iterable<string, array{string}> */
    public static function validJsonCases(): iterable
    {
        yield 'object' => ['{"name": "test", "value": 123}'];
        yield 'array' => ['[1, 2, 3]'];
        yield 'string' => ['"hello"'];
        yield 'number' => ['123'];
        yield 'boolean' => ['true'];
        yield 'null' => ['null'];
        yield 'empty object' => ['{}'];
    }

    #[Test]
    #[DataProvider('invalidJsonCases')]
    public function shouldReturnZeroForInvalidJson(mixed $output): void
    {
        $result = (new IsJson())->score(['output' => $output]);

        self::assertSame(0.0, $result->value);
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidJsonCases(): iterable
    {
        yield 'plain text' => ['not valid json'];
        yield 'trailing comma' => ['{"key": "value",}'];
        yield 'unquoted keys' => ['{key: "value"}'];
        yield 'non-string' => [123];
    }

    #[Test]
    public function shouldUseDefaultName(): void
    {
        $result = (new IsJson())->score(['output' => '{}']);

        self::assertSame('is_json', $result->name);
        self::assertStringContainsString('valid JSON', $result->reason);
    }

    #[Test]
    public function shouldUseCustomName(): void
    {
        $result = (new IsJson('custom'))->score(['output' => '{}']);

        self::assertSame('custom', $result->name);
    }
}
