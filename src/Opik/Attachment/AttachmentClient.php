<?php

declare(strict_types=1);

namespace Opik\Attachment;

use InvalidArgumentException;
use Opik\Api\HttpClientInterface;
use Opik\Config\Config;
use Opik\Exception\OpikException;
use RuntimeException;

/**
 * Client for attachment-related operations.
 *
 * Provides methods to list, upload, and download attachments for traces and spans.
 *
 * @example
 * ```php
 * $attachmentClient = $opikClient->getAttachmentClient();
 *
 * // Upload an attachment to a trace
 * $attachmentClient->uploadAttachment(
 *     projectName: 'my-project',
 *     entityType: AttachmentEntityType::TRACE,
 *     entityId: $trace->getId(),
 *     filePath: '/path/to/file.pdf'
 * );
 *
 * // List attachments for a trace
 * $attachments = $attachmentClient->getAttachmentList(
 *     projectName: 'my-project',
 *     entityType: AttachmentEntityType::TRACE,
 *     entityId: $trace->getId()
 * );
 * ```
 */
final class AttachmentClient
{
    private const LOCAL_UPLOAD_MAGIC_ID = 'BEMinIO';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Config $config,
    ) {
    }

    /**
     * Get a list of attachments for a specific entity (trace or span).
     *
     * @param string $projectName The name of the project containing the entity
     * @param AttachmentEntityType $entityType The type of entity (trace or span)
     * @param string $entityId The ID of the trace or span
     *
     * @throws InvalidArgumentException If any required parameter is empty
     *
     * @return array<int, AttachmentInfo> List of attachment info objects
     */
    public function getAttachmentList(
        string $projectName,
        AttachmentEntityType $entityType,
        string $entityId,
    ): array {
        if (empty(trim($projectName))) {
            throw new InvalidArgumentException('Project name cannot be empty');
        }

        if (empty(trim($entityId))) {
            throw new InvalidArgumentException('Entity ID cannot be empty');
        }

        $projectId = $this->resolveProjectId($projectName);
        $encodedUrlOverride = base64_encode($this->config->baseUrl);

        $response = $this->httpClient->get('v1/private/attachment/list', [
            'project_id' => $projectId,
            'entity_type' => $entityType->value,
            'entity_id' => $entityId,
            'path' => $encodedUrlOverride,
        ]);

        $content = $response['content'] ?? [];

        return array_map(
            fn (array $attachment) => AttachmentInfo::fromArray($attachment),
            $content,
        );
    }

    /**
     * Upload an attachment for a specific entity (trace or span).
     *
     * @param string $projectName The name of the project containing the entity
     * @param AttachmentEntityType $entityType The type of entity (trace or span)
     * @param string $entityId The ID of the trace or span
     * @param string $filePath Path to the file to upload
     * @param string|null $fileName Custom filename (defaults to basename of filePath)
     * @param string|null $mimeType MIME type (auto-detected if not provided)
     *
     * @throws InvalidArgumentException If any required parameter is empty
     * @throws RuntimeException If file does not exist or cannot be read
     */
    public function uploadAttachment(
        string $projectName,
        AttachmentEntityType $entityType,
        string $entityId,
        string $filePath,
        ?string $fileName = null,
        ?string $mimeType = null,
    ): void {
        if (empty(trim($projectName))) {
            throw new InvalidArgumentException('Project name cannot be empty');
        }

        if (empty(trim($entityId))) {
            throw new InvalidArgumentException('Entity ID cannot be empty');
        }

        if (!file_exists($filePath)) {
            throw new RuntimeException("File not found: {$filePath}");
        }

        if (!is_readable($filePath)) {
            throw new RuntimeException("File is not readable: {$filePath}");
        }

        $fileName ??= basename($filePath);
        $mimeType ??= $this->guessMimeType($filePath);
        $fileSize = filesize($filePath);

        if ($fileSize === false) {
            throw new RuntimeException("Cannot determine file size: {$filePath}");
        }

        $encodedUrlOverride = base64_encode($this->config->baseUrl);

        // Calculate number of parts (minimum 5MB per part for S3)
        $minPartSize = 5 * 1024 * 1024; // 5MB
        $numParts = max(1, (int) ceil($fileSize / $minPartSize));

        // Start multipart upload
        $startResponse = $this->httpClient->post('v1/private/attachment/upload-start', [
            'file_name' => $fileName,
            'num_of_file_parts' => $numParts,
            'entity_type' => $entityType->value,
            'entity_id' => $entityId,
            'path' => $encodedUrlOverride,
            'mime_type' => $mimeType,
            'project_name' => $projectName,
        ]);

        $uploadId = $startResponse['upload_id'] ?? null;
        $preSignUrls = $startResponse['pre_sign_urls'] ?? [];

        if (empty($preSignUrls)) {
            throw new OpikException('No upload URLs returned from server');
        }

        // Determine if this is a local upload or S3 upload
        if ($uploadId === null || $uploadId === self::LOCAL_UPLOAD_MAGIC_ID) {
            // Local backend upload
            $this->uploadToLocalBackend($preSignUrls[0], $filePath);
        } else {
            // S3 multipart upload
            $uploadedParts = $this->uploadToS3($preSignUrls, $filePath, $fileSize);

            // Complete the multipart upload
            $this->httpClient->post('v1/private/attachment/upload-complete', [
                'file_name' => $fileName,
                'entity_type' => $entityType->value,
                'entity_id' => $entityId,
                'file_size' => $fileSize,
                'upload_id' => $uploadId,
                'uploaded_file_parts' => $uploadedParts,
                'project_name' => $projectName,
                'mime_type' => $mimeType,
            ]);
        }
    }

    /**
     * Download an attachment's content.
     *
     * @param string $projectName The name of the project containing the entity
     * @param AttachmentEntityType $entityType The type of entity (trace or span)
     * @param string $entityId The ID of the trace or span
     * @param string $fileName The name of the file to download
     * @param string $mimeType The MIME type of the file
     *
     * @throws InvalidArgumentException If any required parameter is empty
     * @throws OpikException If attachment not found
     *
     * @return string The attachment content
     */
    public function downloadAttachment(
        string $projectName,
        AttachmentEntityType $entityType,
        string $entityId,
        string $fileName,
        string $mimeType,
    ): string {
        if (empty(trim($projectName))) {
            throw new InvalidArgumentException('Project name cannot be empty');
        }

        if (empty(trim($entityId))) {
            throw new InvalidArgumentException('Entity ID cannot be empty');
        }

        if (empty(trim($fileName))) {
            throw new InvalidArgumentException('File name cannot be empty');
        }

        // Get the attachment list to find the download link
        $attachments = $this->getAttachmentList($projectName, $entityType, $entityId);

        $attachment = null;
        foreach ($attachments as $att) {
            if ($att->fileName === $fileName && $att->mimeType === $mimeType) {
                $attachment = $att;
                break;
            }
        }

        if ($attachment === null) {
            throw new OpikException("Attachment not found: {$fileName}");
        }

        if ($attachment->link === null) {
            throw new OpikException("No download URL available for attachment: {$fileName}");
        }

        // Download from the link
        $content = file_get_contents($attachment->link);

        if ($content === false) {
            throw new OpikException("Failed to download attachment: {$fileName}");
        }

        return $content;
    }

    /**
     * Resolve project name to project ID.
     */
    private function resolveProjectId(string $projectName): string
    {
        $response = $this->httpClient->get('v1/private/projects', [
            'name' => $projectName,
        ]);

        $projects = $response['content'] ?? [];

        foreach ($projects as $project) {
            if ($project['name'] === $projectName) {
                return $project['id'];
            }
        }

        throw new OpikException("Project not found: {$projectName}");
    }

    /**
     * Guess MIME type from file path.
     */
    private function guessMimeType(string $filePath): ?string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }

        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        return $mimeType !== false ? $mimeType : null;
    }

    /**
     * Upload file to local backend endpoint.
     */
    private function uploadToLocalBackend(string $uploadUrl, string $filePath): void
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException("Cannot read file: {$filePath}");
        }

        // Use cURL for PUT request with raw body
        $ch = curl_init($uploadUrl);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL');
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $content,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/octet-stream',
                'Content-Length: ' . \strlen($content),
            ],
        ]);

        // Add auth headers if needed
        if ($this->config->apiKey !== null) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(
                [
                    'Content-Type: application/octet-stream',
                    'Content-Length: ' . \strlen($content),
                ],
                [
                    'Authorization: ' . $this->config->apiKey,
                    'Comet-Workspace: ' . ($this->config->workspace ?? ''),
                ],
            ));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            throw new OpikException("Failed to upload file: {$error} (HTTP {$httpCode})");
        }
    }

    /**
     * Upload file parts to S3 using pre-signed URLs.
     *
     * @param array<int, string> $preSignUrls Pre-signed URLs for each part
     * @param string $filePath Path to the file
     * @param int $fileSize Total file size
     *
     * @return array<int, array{e_tag: string, part_number: int}> Uploaded parts info
     */
    private function uploadToS3(array $preSignUrls, string $filePath, int $fileSize): array
    {
        $uploadedParts = [];
        $numParts = \count($preSignUrls);
        $partSize = (int) ceil($fileSize / $numParts);

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Cannot open file: {$filePath}");
        }

        try {
            foreach ($preSignUrls as $partNumber => $url) {
                $offset = $partNumber * $partSize;
                $length = min($partSize, $fileSize - $offset);

                fseek($handle, $offset);
                $data = fread($handle, $length);

                if ($data === false) {
                    throw new RuntimeException("Failed to read file part {$partNumber}");
                }

                $eTag = $this->uploadPartToS3($url, $data);

                $uploadedParts[] = [
                    'e_tag' => $eTag,
                    'part_number' => $partNumber + 1, // S3 parts are 1-indexed
                ];
            }
        } finally {
            fclose($handle);
        }

        return $uploadedParts;
    }

    /**
     * Upload a single part to S3.
     */
    private function uploadPartToS3(string $url, string $data): string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL');
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/octet-stream',
                'Content-Length: ' . \strlen($data),
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            throw new OpikException("Failed to upload part to S3: {$error} (HTTP {$httpCode})");
        }

        // Extract ETag from response headers
        $headers = substr((string) $response, 0, $headerSize);
        if (preg_match('/ETag:\s*"?([^"\r\n]+)"?/i', $headers, $matches)) {
            return $matches[1];
        }

        throw new OpikException('Failed to get ETag from S3 response');
    }
}
