<?php

declare(strict_types=1);

namespace Opik\Exception;

use Throwable;

final class ApiException extends OpikException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly ?string $responseBody = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
