<?php

namespace Tests\Unit;

use App\Exceptions\AppException;
use App\Exceptions\Domain\FileUploadExceededException;
use PHPUnit\Framework\TestCase;

class FileUploadExceededExceptionTest extends TestCase
{
    public function test_extends_app_exception(): void
    {
        $exception = new FileUploadExceededException('Error message', 10485760);

        $this->assertInstanceOf(AppException::class, $exception);
        $this->assertSame(413, $exception->getHttpStatus());
        $this->assertSame(10485760, $exception->getMaxBytes());
        $this->assertFalse($exception->shouldReport());
        $this->assertSame(['max_bytes' => 10485760], $exception->context());
    }

    public function test_parse_ini_size(): void
    {
        $this->assertSame(10485760, FileUploadExceededException::parseIniSize('10M'));
        $this->assertSame(10485760, FileUploadExceededException::parseIniSize('10m'));
        $this->assertSame(1073741824, FileUploadExceededException::parseIniSize('1G'));
        $this->assertSame(1024, FileUploadExceededException::parseIniSize('1K'));
        $this->assertSame(500, FileUploadExceededException::parseIniSize('500'));
        $this->assertSame(0, FileUploadExceededException::parseIniSize(''));
        $this->assertSame(0, FileUploadExceededException::parseIniSize(null));
    }

    public function test_format_bytes(): void
    {
        $this->assertSame('10MB', FileUploadExceededException::formatBytes(10485760));
        $this->assertSame('1GB', FileUploadExceededException::formatBytes(1073741824));
        $this->assertSame('500B', FileUploadExceededException::formatBytes(500));
        $this->assertSame('0B', FileUploadExceededException::formatBytes(0));
    }

    public function test_from_ini_limits_constructs_user_friendly_message(): void
    {
        $exception = FileUploadExceededException::fromIniLimits(receivedBytes: 20971520);

        $this->assertStringStartsWith('O arquivo enviado excede o limite máximo permitido de', $exception->getMessage());
        $this->assertSame(413, $exception->getHttpStatus());
        $this->assertArrayHasKey('max_bytes', $exception->context());
        $this->assertArrayHasKey('formatted_limit', $exception->context());
        $this->assertSame(20971520, $exception->context()['received_bytes']);
        $this->assertSame('20MB', $exception->context()['formatted_received']);
    }
}
