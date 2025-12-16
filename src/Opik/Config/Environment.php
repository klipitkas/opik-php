<?php

declare(strict_types=1);

namespace Opik\Config;

final readonly class Environment
{
    public const string OPIK_API_KEY = 'OPIK_API_KEY';

    public const string OPIK_WORKSPACE = 'OPIK_WORKSPACE';

    public const string OPIK_PROJECT_NAME = 'OPIK_PROJECT_NAME';

    public const string OPIK_URL_OVERRIDE = 'OPIK_URL_OVERRIDE';

    public const string OPIK_DEBUG = 'OPIK_DEBUG';

    public const string OPIK_ENABLE_COMPRESSION = 'OPIK_ENABLE_COMPRESSION';

    public static function get(string $name, ?string $default = null): ?string
    {
        $value = \getenv($name);

        if ($value === false) {
            return $default;
        }

        return $value;
    }

    public static function getBool(string $name, bool $default = false): bool
    {
        $value = self::get($name);

        if ($value === null) {
            return $default;
        }

        return \in_array(\strtolower($value), ['true', '1', 'yes', 'on'], true);
    }
}
