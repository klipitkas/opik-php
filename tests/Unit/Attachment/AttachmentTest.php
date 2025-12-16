<?php

declare(strict_types=1);

namespace Opik\Tests\Unit\Attachment;

use Opik\Attachment\Attachment;
use Opik\Attachment\AttachmentEntityType;
use Opik\Attachment\AttachmentInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AttachmentTest extends TestCase
{
    #[Test]
    public function shouldCreateAttachmentWithFilePath(): void
    {
        $attachment = new Attachment(
            filePath: '/path/to/file.pdf',
        );

        self::assertSame('/path/to/file.pdf', $attachment->filePath);
        self::assertSame('file.pdf', $attachment->fileName);
        self::assertNull($attachment->mimeType);
    }

    #[Test]
    public function shouldCreateAttachmentWithCustomFileName(): void
    {
        $attachment = new Attachment(
            filePath: '/path/to/file.pdf',
            fileName: 'custom-name.pdf',
        );

        self::assertSame('/path/to/file.pdf', $attachment->filePath);
        self::assertSame('custom-name.pdf', $attachment->fileName);
    }

    #[Test]
    public function shouldCreateAttachmentWithMimeType(): void
    {
        $attachment = new Attachment(
            filePath: '/path/to/file.pdf',
            mimeType: 'application/pdf',
        );

        self::assertSame('application/pdf', $attachment->mimeType);
    }

    #[Test]
    public function shouldHaveTraceEntityType(): void
    {
        self::assertSame('trace', AttachmentEntityType::TRACE->value);
    }

    #[Test]
    public function shouldHaveSpanEntityType(): void
    {
        self::assertSame('span', AttachmentEntityType::SPAN->value);
    }

    #[Test]
    public function shouldCreateAttachmentInfoFromArray(): void
    {
        $data = [
            'file_name' => 'report.pdf',
            'file_size' => 12345,
            'mime_type' => 'application/pdf',
            'link' => 'https://example.com/download/report.pdf',
        ];

        $info = AttachmentInfo::fromArray($data);

        self::assertSame('report.pdf', $info->fileName);
        self::assertSame(12345, $info->fileSize);
        self::assertSame('application/pdf', $info->mimeType);
        self::assertSame('https://example.com/download/report.pdf', $info->link);
    }

    #[Test]
    public function shouldCreateAttachmentInfoWithoutLink(): void
    {
        $data = [
            'file_name' => 'image.png',
            'file_size' => 5000,
            'mime_type' => 'image/png',
        ];

        $info = AttachmentInfo::fromArray($data);

        self::assertSame('image.png', $info->fileName);
        self::assertSame(5000, $info->fileSize);
        self::assertSame('image/png', $info->mimeType);
        self::assertNull($info->link);
    }

    #[Test]
    public function shouldCreateAttachmentInfoDirectly(): void
    {
        $info = new AttachmentInfo(
            fileName: 'document.docx',
            fileSize: 8000,
            mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            link: 'https://example.com/doc',
        );

        self::assertSame('document.docx', $info->fileName);
        self::assertSame(8000, $info->fileSize);
        self::assertSame('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $info->mimeType);
        self::assertSame('https://example.com/doc', $info->link);
    }
}
