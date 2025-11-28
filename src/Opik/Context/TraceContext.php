<?php

declare(strict_types=1);

namespace Opik\Context;

use Opik\Tracer\Span;
use Opik\Tracer\Trace;
use WeakReference;

final class TraceContext
{
    /** @var WeakReference<Trace>|null */
    private static ?WeakReference $currentTrace = null;

    /** @var WeakReference<Span>|null */
    private static ?WeakReference $currentSpan = null;

    /** @var array<int, WeakReference<Span>> */
    private static array $spanStack = [];

    public static function setCurrentTrace(?Trace $trace): void
    {
        self::$currentTrace = $trace !== null ? WeakReference::create($trace) : null;
    }

    public static function getCurrentTrace(): ?Trace
    {
        return self::$currentTrace?->get();
    }

    public static function setCurrentSpan(?Span $span): void
    {
        self::$currentSpan = $span !== null ? WeakReference::create($span) : null;
    }

    public static function getCurrentSpan(): ?Span
    {
        return self::$currentSpan?->get();
    }

    public static function pushSpan(Span $span): void
    {
        $currentSpan = self::$currentSpan?->get();
        if ($currentSpan !== null) {
            self::$spanStack[] = self::$currentSpan;
        }

        self::$currentSpan = WeakReference::create($span);
    }

    public static function popSpan(): ?Span
    {
        $popped = self::$currentSpan?->get();
        $weakRef = \array_pop(self::$spanStack);
        self::$currentSpan = $weakRef;

        return $popped;
    }

    public static function clear(): void
    {
        self::$currentTrace = null;
        self::$currentSpan = null;
        self::$spanStack = [];
    }

    public static function hasActiveTrace(): bool
    {
        return self::$currentTrace?->get() !== null;
    }

    public static function hasActiveSpan(): bool
    {
        return self::$currentSpan?->get() !== null;
    }
}
