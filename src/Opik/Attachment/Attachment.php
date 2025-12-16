<?php

declare(strict_types=1);

namespace Opik\Attachment;

/**
 * Represents an attachment to be added to a Trace or Span.
 *
 * @example
 * ```php
 * $attachment = new Attachment(
 *     filePath: '/path/to/file.pdf',
 *     fileName: 'report.pdf', // optional, defaults to basename of filePath
 *     mimeType: 'application/pdf' // optional, auto-detected if not provided
 * );
 * ```
 */
final class Attachment
{
    public readonly string $filePath;

    public readonly string $fileName;

    public readonly ?string $mimeType;

    /**
     * Create a new attachment.
     *
     * @param string $filePath Path to the file to attach
     * @param string|null $fileName Custom filename (defaults to basename of filePath)
     * @param string|null $mimeType MIME type (auto-detected if not provided)
     */
    public function __construct(
        string $filePath,
        ?string $fileName = null,
        ?string $mimeType = null,
    ) {
        $this->filePath = $filePath;
        $this->fileName = $fileName ?? basename($filePath);
        $this->mimeType = $mimeType;
    }
}
