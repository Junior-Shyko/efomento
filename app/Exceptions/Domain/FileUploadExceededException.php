<?php

namespace App\Exceptions\Domain;

use App\Exceptions\AppException;
use App\Support\UploadLimits;

final class FileUploadExceededException extends AppException
{
    public function __construct(
        string $userMessage,
        private readonly int $maxBytes,
        int $httpStatus = 413,
        array $context = [],
        ?\Throwable $previous = null,
    ) {
        $fullContext = array_merge([
            'max_bytes' => $maxBytes,
        ], $context);

        parent::__construct($userMessage, httpStatus: $httpStatus, context: $fullContext, previous: $previous);
    }

    public static function fromIniLimits(?int $receivedBytes = null, ?\Throwable $previous = null): self
    {
        $maxBytes = self::determineMaxUploadBytes();
        $formattedLimit = self::formatBytes($maxBytes);

        $message = "O arquivo enviado excede o limite máximo permitido de {$formattedLimit}.";

        $context = [
            'max_bytes' => $maxBytes,
            'formatted_limit' => $formattedLimit,
        ];

        if ($receivedBytes !== null) {
            $context['received_bytes'] = $receivedBytes;
            $context['formatted_received'] = self::formatBytes($receivedBytes);
        }

        return new self(
            userMessage: $message,
            maxBytes: $maxBytes,
            httpStatus: 413,
            context: $context,
            previous: $previous,
        );
    }

    public function getMaxBytes(): int
    {
        return $this->maxBytes;
    }

    public function shouldReport(): bool
    {
        return false;
    }

    public static function determineMaxUploadBytes(): int
    {
        return UploadLimits::determineMaxUploadBytes();
    }

    public static function parseIniSize(?string $size): int
    {
        return UploadLimits::parseIniSize($size);
    }

    public static function formatBytes(int $bytes, int $precision = 0): string
    {
        return UploadLimits::formatBytes($bytes, $precision);
    }
}
