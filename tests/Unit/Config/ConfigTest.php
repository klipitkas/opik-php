<?php

declare(strict_types=1);

namespace Opik\Tests\Unit\Config;

use Opik\Config\Config;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('OPIK_API_KEY');
        putenv('OPIK_WORKSPACE');
        putenv('OPIK_PROJECT_NAME');
        putenv('OPIK_URL_OVERRIDE');
    }

    #[Test]
    public function shouldUseDefaultLocalUrlWhenNoApiKey(): void
    {
        $config = new Config();

        self::assertSame(Config::LOCAL_BASE_URL, $config->baseUrl);
        self::assertFalse($config->isCloud());
    }

    #[Test]
    public function shouldUseCloudUrlWhenApiKeyProvided(): void
    {
        $config = new Config(apiKey: 'test-api-key');

        self::assertSame(Config::CLOUD_BASE_URL, $config->baseUrl);
        self::assertTrue($config->isCloud());
    }

    #[Test]
    public function shouldUseCustomUrlWhenProvided(): void
    {
        $config = new Config(baseUrl: 'https://custom.example.com/api');

        self::assertSame('https://custom.example.com/api/', $config->baseUrl);
    }

    #[Test]
    public function shouldReadApiKeyFromEnvironment(): void
    {
        putenv('OPIK_API_KEY=env-api-key');

        $config = new Config();

        self::assertSame('env-api-key', $config->apiKey);
    }

    #[Test]
    public function shouldPreferConstructorOverEnvironment(): void
    {
        putenv('OPIK_API_KEY=env-api-key');

        $config = new Config(apiKey: 'constructor-api-key');

        self::assertSame('constructor-api-key', $config->apiKey);
    }

    #[Test]
    public function shouldReadWorkspaceFromEnvironment(): void
    {
        putenv('OPIK_WORKSPACE=test-workspace');

        $config = new Config();

        self::assertSame('test-workspace', $config->workspace);
    }

    #[Test]
    public function shouldReadProjectNameFromEnvironment(): void
    {
        putenv('OPIK_PROJECT_NAME=test-project');

        $config = new Config();

        self::assertSame('test-project', $config->projectName);
    }

    #[Test]
    public function shouldUseDefaultProjectNameWhenNotProvided(): void
    {
        $config = new Config();

        self::assertSame('Default Project', $config->projectName);
    }

    #[Test]
    public function shouldReadUrlOverrideFromEnvironment(): void
    {
        putenv('OPIK_URL_OVERRIDE=https://override.example.com/api');

        $config = new Config();

        self::assertSame('https://override.example.com/api/', $config->baseUrl);
    }

    #[Test]
    public function shouldRequireAuthenticationForCloud(): void
    {
        $config = new Config(apiKey: 'test-key');

        self::assertTrue($config->requiresAuthentication());
    }

    #[Test]
    public function shouldNotRequireAuthenticationForLocal(): void
    {
        $config = new Config();

        self::assertFalse($config->requiresAuthentication());
    }
}
