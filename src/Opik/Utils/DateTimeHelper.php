<?php

declare(strict_types=1);

namespace Opik\Utils;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Helper class for date/time operations with consistent UTC timezone handling.
 */
final class DateTimeHelper
{
    private static ?DateTimeZone $utc = null;

    /**
     * Get the current date/time in UTC.
     *
     * @return DateTimeImmutable Current timestamp in UTC
     */
    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', self::utc());
    }

    /**
     * Format a date/time for API transmission.
     *
     * Uses RFC3339 extended format with microseconds for maximum precision.
     *
     * @param DateTimeInterface $dateTime The date/time to format
     *
     * @return string The formatted date/time string
     */
    public static function format(DateTimeInterface $dateTime): string
    {
        return $dateTime->format(DateTimeInterface::RFC3339_EXTENDED);
    }

    /**
     * Get the UTC timezone instance (cached for performance).
     *
     * @return DateTimeZone The UTC timezone
     */
    public static function utc(): DateTimeZone
    {
        return self::$utc ??= new DateTimeZone('UTC');
    }
}
