<?php

declare(strict_types=1);

namespace Opik\Tests\Integration;

use Opik\OpikClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for authentication against Opik Cloud.
 *
 * These tests require the following environment variables:
 * - OPIK_API_KEY: API key for authentication
 * - OPIK_WORKSPACE: Workspace name
 */
final class AuthenticationIntegrationTest extends TestCase
{
    #[Test]
    public function shouldAuthenticateWithValidCredentials(): void
    {
        $apiKey = getenv('OPIK_API_KEY');
        $workspace = getenv('OPIK_WORKSPACE');

        if ($apiKey === false || $apiKey === '') {
            self::markTestSkipped('OPIK_API_KEY environment variable not set');
        }

        if ($workspace === false || $workspace === '') {
            self::markTestSkipped('OPIK_WORKSPACE environment variable not set');
        }

        $client = new OpikClient(
            apiKey: $apiKey,
            workspace: $workspace,
        );

        self::assertTrue($client->authCheck(), 'Authentication should succeed with valid credentials');
    }

    #[Test]
    public function shouldFailAuthenticationWithInvalidApiKey(): void
    {
        $workspace = getenv('OPIK_WORKSPACE');

        if ($workspace === false || $workspace === '') {
            self::markTestSkipped('OPIK_WORKSPACE environment variable not set');
        }

        $client = new OpikClient(
            apiKey: 'invalid-api-key-12345',
            workspace: $workspace,
        );

        self::assertFalse($client->authCheck(), 'Authentication should fail with invalid API key');
    }

    #[Test]
    public function shouldFailAuthenticationWithInvalidWorkspace(): void
    {
        $apiKey = getenv('OPIK_API_KEY');

        if ($apiKey === false || $apiKey === '') {
            self::markTestSkipped('OPIK_API_KEY environment variable not set');
        }

        $client = new OpikClient(
            apiKey: $apiKey,
            workspace: 'invalid-workspace-that-does-not-exist',
        );

        self::assertFalse($client->authCheck(), 'Authentication should fail with invalid workspace');
    }
}
