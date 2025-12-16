<?php

declare(strict_types=1);

namespace Opik\Api;

interface HttpClientInterface
{
    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function post(string $endpoint, array $data = []): array;

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    public function get(string $endpoint, array $query = []): array;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function put(string $endpoint, array $data = []): array;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function patch(string $endpoint, array $data = []): array;

    public function delete(string $endpoint): void;
}
