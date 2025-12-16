<?php

declare(strict_types=1);

namespace Opik\Decorator;

use Closure;
use Opik\Context\TraceContext;
use Opik\OpikClient;
use Opik\Tracer\ErrorInfo;
use Opik\Tracer\Span;
use Opik\Tracer\SpanType;
use ReflectionFunction;
use Throwable;

final class TrackHandler
{
    private static ?OpikClient $client = null;

    public static function setClient(OpikClient $client): void
    {
        self::$client = $client;
    }

    public static function getClient(): ?OpikClient
    {
        return self::$client;
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public static function track(
        callable $callback,
        ?string $name = null,
        ?string $projectName = null,
        SpanType $type = SpanType::GENERAL,
        bool $captureInput = true,
        bool $captureOutput = true,
    ): mixed {
        if (self::$client === null) {
            return $callback();
        }

        $resolvedName = $name ?? self::resolveName($callback);
        $isRootSpan = !TraceContext::hasActiveTrace();
        $input = $captureInput ? self::captureArguments($callback) : null;

        if ($isRootSpan) {
            $trace = self::$client->trace(
                name: $resolvedName,
                projectName: $projectName,
                input: $input,
            );
            TraceContext::setCurrentTrace($trace);

            $span = $trace->span(
                name: $resolvedName,
                type: $type,
                input: $input,
            );
        } else {
            $parentSpan = TraceContext::getCurrentSpan();
            $trace = TraceContext::getCurrentTrace();

            $span = new Span(
                batchQueue: self::getBatchQueue(),
                traceId: $trace->getId(),
                name: $resolvedName,
                projectName: $projectName ?? $trace->getProjectName(),
                parentSpanId: $parentSpan?->getId(),
                type: $type,
                input: $input,
            );
        }

        TraceContext::pushSpan($span);

        try {
            $result = $callback();

            $output = $captureOutput ? self::normalizeOutput($result) : null;
            $span->update(output: $output);
            $span->end();

            if ($isRootSpan) {
                $trace->update(output: $output);
                $trace->end();
            }

            return $result;
        } catch (Throwable $e) {
            $errorInfo = ErrorInfo::fromThrowable($e);
            $span->update(errorInfo: $errorInfo);
            $span->end();

            if ($isRootSpan) {
                $trace->update(errorInfo: $errorInfo);
                $trace->end();
            }

            throw $e;
        } finally {
            TraceContext::popSpan();

            if ($isRootSpan) {
                TraceContext::clear();
            }
        }
    }

    private static function captureArguments(callable $callback): mixed
    {
        unset($callback);

        return null;
    }

    private static function resolveName(callable $callback): string
    {
        if ($callback instanceof Closure) {
            $reflection = new ReflectionFunction($callback);

            return $reflection->getName() !== '{closure}'
                ? $reflection->getName()
                : 'anonymous';
        }

        if (\is_array($callback)) {
            $class = \is_object($callback[0]) ? $callback[0]::class : $callback[0];

            return $class . '::' . $callback[1];
        }

        if (\is_string($callback)) {
            return $callback;
        }

        return 'callable';
    }

    private static function normalizeOutput(mixed $result): mixed
    {
        if ($result === null) {
            return null;
        }

        if (\is_scalar($result)) {
            return ['result' => $result];
        }

        if (\is_array($result)) {
            return $result;
        }

        if (\is_object($result)) {
            if (method_exists($result, 'toArray')) {
                return $result->toArray();
            }

            if (method_exists($result, '__toString')) {
                return ['result' => (string) $result];
            }

            return get_object_vars($result);
        }

        return ['result' => $result];
    }

    private static function getBatchQueue(): \Opik\Message\BatchQueue
    {
        return self::$client->getBatchQueue();
    }
}
