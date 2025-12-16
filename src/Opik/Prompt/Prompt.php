<?php

declare(strict_types=1);

namespace Opik\Prompt;

use Opik\Api\HttpClientInterface;
use Opik\Exception\OpikException;

final class Prompt
{
    private ?PromptVersion $latestVersion = null;

    /** @var array<string, PromptVersion> */
    private array $versions = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        public readonly string $id,
        public readonly string $name,
    ) {
    }

    public function getLatestVersion(): PromptVersion
    {
        if ($this->latestVersion === null) {
            $response = $this->httpClient->get("v1/private/prompts/{$this->id}/versions", [
                'size' => 1,
            ]);

            $versions = $response['content'] ?? [];

            if ($versions === []) {
                throw new OpikException("No versions found for prompt: {$this->name}");
            }

            $this->latestVersion = PromptVersion::fromArray($versions[0]);
        }

        return $this->latestVersion;
    }

    public function getVersion(string $commit): PromptVersion
    {
        if (isset($this->versions[$commit])) {
            return $this->versions[$commit];
        }

        $response = $this->httpClient->get("v1/private/prompts/{$this->id}/versions", [
            'commit' => $commit,
        ]);

        $versions = $response['content'] ?? [];

        if ($versions === []) {
            throw new OpikException("Version not found for prompt: {$this->name}, commit: {$commit}");
        }

        $this->versions[$commit] = PromptVersion::fromArray($versions[0]);

        return $this->versions[$commit];
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function format(array $variables = [], ?string $commit = null): string|array
    {
        $version = $commit !== null
            ? $this->getVersion($commit)
            : $this->getLatestVersion();

        return $version->format($variables);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
