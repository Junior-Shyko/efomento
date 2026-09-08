<?php

namespace App\Exceptions\Domain;

use App\Exceptions\AppException;

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
        $postMax = self::parseIniSize(ini_get('post_max_size'));
        $uploadMax = self::parseIniSize(ini_get('upload_max_filesize'));

        if ($postMax > 0 && $uploadMax > 0) {
            return min($postMax, $uploadMax);
        }

        return max($postMax, $uploadMax, 0);
    }

    public static function parseIniSize(?string $size): int
    {
        if (blank($size)) {
            return 0;
        }

        $size = trim($size);
        $last = strtolower($size[strlen($size) - 1]);
        $val = (int) $size;

        return match ($last) {
            'g' => $val * 1024 * 1024 * 1024,
            'm' => $val * 1024 * 1024,
            'k' => $val * 1024,
            default => (int) $size,
        };
    }

    public static function formatBytes(int $bytes, int $precision = 0): string
    {
        if ($bytes <= 0) {
            return '0B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision).$units[$pow];
    }
}
