<?php

namespace App\Support;

final class UploadLimits
{
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

    public static function maxUploadMb(): int
    {
        return (int) round(self::determineMaxUploadBytes() / 1024 / 1024);
    }
}
