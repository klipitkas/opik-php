<?php

declare(strict_types=1);

namespace Opik\Utils;

use Ramsey\Uuid\Uuid;

/**
 * Generates unique identifiers for traces, spans, and other entities.
 */
final readonly class IdGenerator
{
    /**
     * Generate a new UUID v7 identifier.
     *
     * UUID v7 is time-ordered, which provides better database indexing performance
     * compared to random UUIDs.
     *
     * @return string The generated UUID string
     */
    public static function uuid(): string
    {
        return Uuid::uuid7()->toString();
    }
}
