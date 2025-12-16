<?php

declare(strict_types=1);

namespace Opik\Attachment;

/**
 * Information about an uploaded attachment.
 */
final class AttachmentInfo
{
    public function __construct(
        public readonly string $fileName,
        public readonly int $fileSize,
        public readonly string $mimeType,
        public readonly ?string $link = null,
    ) {
    }

    /**
     * Create from array (e.g., from API response).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            fileName: $data['file_name'],
            fileSize: (int) $data['file_size'],
            mimeType: $data['mime_type'],
            link: $data['link'] ?? null,
        );
    }
}
