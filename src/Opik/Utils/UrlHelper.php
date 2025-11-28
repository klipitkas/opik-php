<?php

declare(strict_types=1);

namespace Opik\Utils;

/**
 * Helper class for URL manipulation and construction.
 */
final readonly class UrlHelper
{
    /**
     * Join URL path segments together.
     *
     * @param string $base The base URL
     * @param string ...$paths Additional path segments to append
     * @return string The joined URL
     */
    public static function join(string $base, string ...$paths): string
    {
        $url = \rtrim($base, '/');

        foreach ($paths as $path) {
            $url .= '/' . \ltrim($path, '/');
        }

        return $url;
    }

    /**
     * Build a query string from parameters, filtering out null values.
     *
     * @param array<string, mixed> $params The query parameters
     * @return string The query string (including '?' prefix) or empty string if no params
     */
    public static function buildQuery(array $params): string
    {
        $filtered = \array_filter($params, static fn ($value) => $value !== null);

        if ($filtered === []) {
            return '';
        }

        return '?' . \http_build_query($filtered);
    }
}
