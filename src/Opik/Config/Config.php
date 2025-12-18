<?php

declare(strict_types=1);

namespace Opik\Config;

final class Config
{
    public const CLOUD_BASE_URL = 'https://www.comet.com/opik/api/';

    public const LOCAL_BASE_URL = 'http://localhost:5173/api/';

    public const DEFAULT_TIMEOUT_MS = 30000;

    public const MAX_BATCH_SIZE_BYTES = 1 * 1024 * 1024; // 1 MB batch size limit

    public const MAX_BATCH_COUNT = 25; // Maximum number of items per batch

    public const FLUSH_INTERVAL_MS = 10000; // Flush every 10 seconds

    public const MAX_RETRIES = 3;

    public const RETRY_BASE_DELAY_MS = 100;

    public readonly ?string $apiKey;

    public readonly ?string $workspace;

    public readonly ?string $projectName;

    public readonly string $baseUrl;

    public readonly bool $debug;

    public readonly bool $enableCompression;

    public function __construct(
        ?string $apiKey = null,
        ?string $workspace = null,
        ?string $projectName = null,
        ?string $baseUrl = null,
        bool $debug = false,
        ?bool $enableCompression = null,
    ) {
        $this->apiKey = $apiKey ?? Environment::get(Environment::OPIK_API_KEY);
        $this->workspace = $workspace ?? Environment::get(Environment::OPIK_WORKSPACE);
        $this->projectName = $projectName ?? Environment::get(Environment::OPIK_PROJECT_NAME, 'Default Project');
        $this->debug = $debug || Environment::getBool(Environment::OPIK_DEBUG);
        $this->enableCompression = $enableCompression ?? Environment::getBool(Environment::OPIK_ENABLE_COMPRESSION, true);

        $resolvedBaseUrl = $baseUrl
            ?? Environment::get(Environment::OPIK_URL_OVERRIDE)
            ?? ($this->apiKey !== null ? self::CLOUD_BASE_URL : self::LOCAL_BASE_URL);

        $this->baseUrl = rtrim($resolvedBaseUrl, '/') . '/';
    }

    public function isCloud(): bool
    {
        return str_contains($this->baseUrl, 'comet.com');
    }

    public function requiresAuthentication(): bool
    {
        return $this->isCloud();
    }
}
