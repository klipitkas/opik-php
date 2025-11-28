<?php

declare(strict_types=1);

namespace Opik\Utils;

use JsonException;

/**
 * JSON encoding/decoding utility with sanitization support.
 *
 * Handles encoding of complex objects and provides fallback sanitization
 * for data that cannot be directly JSON encoded.
 */
final readonly class JsonEncoder
{
    private const int ENCODE_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * Encode data to JSON string with automatic sanitization fallback.
     *
     * If direct encoding fails, the data is sanitized and re-encoded.
     * This handles cases like resources, non-serializable objects, etc.
     *
     * @param mixed $data The data to encode
     * @return string The JSON string
     */
    public static function encode(mixed $data): string
    {
        try {
            return \json_encode($data, self::ENCODE_FLAGS);
        } catch (JsonException $e) {
            return \json_encode(self::sanitize($data), self::ENCODE_FLAGS);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function decode(string $json): array
    {
        return \json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Sanitize data for JSON encoding.
     *
     * Converts non-serializable types to serializable equivalents:
     * - DateTimeInterface: RFC3339 string
     * - Objects with toArray(): array representation
     * - Objects with __toString(): string representation
     * - Other objects: public properties as array
     * - Resources: '[resource]' placeholder
     *
     * @param mixed $data The data to sanitize
     * @return mixed The sanitized data
     */
    private static function sanitize(mixed $data): mixed
    {
        if (\is_array($data)) {
            return \array_map(self::sanitize(...), $data);
        }

        if (\is_object($data)) {
            if ($data instanceof \DateTimeInterface) {
                return $data->format(\DateTimeInterface::RFC3339_EXTENDED);
            }

            if (\method_exists($data, 'toArray')) {
                return self::sanitize($data->toArray());
            }

            if (\method_exists($data, '__toString')) {
                return (string) $data;
            }

            return self::sanitize(\get_object_vars($data));
        }

        if (\is_resource($data)) {
            return '[resource]';
        }

        return $data;
    }
}
