<?php

declare(strict_types=1);

namespace Opik\Tracer;

use Throwable;

final class ErrorInfo
{
    public readonly string $message;

    public readonly string $exceptionType;

    public readonly string $traceback;

    public function __construct(
        string $message,
        string $exceptionType,
        string $traceback = '',
    ) {
        $this->message = $message;
        $this->exceptionType = $exceptionType;
        $this->traceback = $traceback;
    }

    public static function fromThrowable(Throwable $e): self
    {
        return new self(
            message: $e->getMessage(),
            exceptionType: $e::class,
            traceback: $e->getTraceAsString(),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'exception_type' => $this->exceptionType,
            'traceback' => $this->traceback,
        ];
    }
}
