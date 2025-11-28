<?php

declare(strict_types=1);

namespace Opik\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Opik\Config\Config;
use Opik\Exception\ApiException;
use Opik\Utils\JsonEncoder;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class HttpClient implements HttpClientInterface
{
    private readonly Client $client;

    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly Config $config,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->client = new Client([
            'base_uri' => $config->baseUrl,
            'timeout' => $config::DEFAULT_TIMEOUT_MS / 1000,
            'headers' => $this->buildHeaders(),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, $data);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function get(string $endpoint, array $query = []): array
    {
        return $this->request('GET', $endpoint, query: $query);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function put(string $endpoint, array $data = []): array
    {
        return $this->request('PUT', $endpoint, $data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function patch(string $endpoint, array $data = []): array
    {
        return $this->request('PATCH', $endpoint, $data);
    }

    public function delete(string $endpoint): void
    {
        $this->request('DELETE', $endpoint);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function request(
        string $method,
        string $endpoint,
        array $data = [],
        array $query = [],
    ): array {
        $attempt = 0;
        $lastException = null;

        while ($attempt < Config::MAX_RETRIES) {
            try {
                $options = [];

                if ($data !== []) {
                    $options[RequestOptions::JSON] = $data;
                }

                if ($query !== []) {
                    $options[RequestOptions::QUERY] = $query;
                }

                $this->logger->debug('API request', [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'attempt' => $attempt + 1,
                ]);

                $response = $this->client->request($method, $endpoint, $options);
                $body = $response->getBody()->getContents();

                if ($body === '') {
                    return [];
                }

                return JsonEncoder::decode($body);
            } catch (RequestException $e) {
                $lastException = $e;
                $statusCode = $e->getResponse()?->getStatusCode() ?? 0;

                if (!$this->isRetryable($statusCode)) {
                    throw $this->createApiException($e);
                }

                $attempt++;
                $this->logger->warning('API request failed, retrying', [
                    'attempt' => $attempt,
                    'status_code' => $statusCode,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < Config::MAX_RETRIES) {
                    $this->sleep($attempt);
                }
            } catch (GuzzleException $e) {
                throw new ApiException(
                    'HTTP request failed: ' . $e->getMessage(),
                    previous: $e,
                );
            }
        }

        throw $this->createApiException($lastException);
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($this->config->apiKey !== null) {
            $headers['authorization'] = $this->config->apiKey;
        }

        if ($this->config->workspace !== null) {
            $headers['Comet-Workspace'] = $this->config->workspace;
        }

        return $headers;
    }

    private function isRetryable(int $statusCode): bool
    {
        return \in_array($statusCode, [408, 429, 500, 502, 503, 504], true);
    }

    /**
     * Sleep with exponential backoff and jitter to prevent thundering herd.
     *
     * @param int $attempt The current retry attempt number (1-based)
     */
    private function sleep(int $attempt): void
    {
        $delayMs = Config::RETRY_BASE_DELAY_MS * (2 ** ($attempt - 1));
        $jitter = \random_int(0, (int) ($delayMs * 0.1));
        $totalDelayMs = $delayMs + $jitter;

        $this->logger->debug('Retry backoff', [
            'attempt' => $attempt,
            'delay_ms' => $delayMs,
            'jitter_ms' => $jitter,
            'total_delay_ms' => $totalDelayMs,
        ]);

        \usleep($totalDelayMs * 1000);
    }

    private function createApiException(?RequestException $e): ApiException
    {
        if ($e === null) {
            return new ApiException('Request failed after maximum retries');
        }

        $response = $e->getResponse();
        $statusCode = $response?->getStatusCode() ?? 0;
        $body = $response?->getBody()->getContents();

        return new ApiException(
            'API request failed: ' . $e->getMessage(),
            $statusCode,
            $body,
            $e,
        );
    }
}
