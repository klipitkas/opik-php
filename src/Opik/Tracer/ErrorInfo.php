<?php

declare(strict_types=1);

namespace Opik\Tracer;

final readonly class ErrorInfo
{
    public function __construct(
        public string $message,
        public string $exceptionType,
        public string $traceback = '',
    ) {}

    public static function fromThrowable(\Throwable $e): self
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
