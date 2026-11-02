<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class EditorImage
{
    const FOLDER = 'editor-images';
    const MAX_UPLOAD_KB = 2048;
    const MAX_WIDTH = 1600;
    const JPEG_QUALITY = 85;
    const JPEG_MIN_SAVING = 0.5;
    const PALETTE_MAX_AVG_DIFF = 4.0;
    const PALETTE_MAX_OUTLIER_RATIO = 0.005;
    const PALETTE_OUTLIER_DIFF = 24;
    const SAMPLE_PIXELS = 40000;
    const DATA_URI_PATTERN = '#data:image/(?:png|jpe?g|gif|webp|bmp);base64,([A-Za-z0-9+/=]+)#i';
    const EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/bmp' => 'bmp',
        'image/x-ms-bmp' => 'bmp',
    ];

    public static function store(string $binary, bool $write = true): ?array
    {
        $info = @getimagesizefromstring($binary);
        $mime = $info['mime'] ?? null;
        if (!$mime || !isset(self::EXTENSIONS[$mime])) {
            return null;
        }

        [$bytes, $extension] = self::compress($binary, $mime);
        $hash = sha1($binary);
        $path = self::FOLDER . '/' . substr($hash, 0, 2) . '/' . $hash . '.' . $extension;

        $disk = Storage::disk('public');
        $existed = $disk->exists($path);
        if ($write && !$existed) {
            $disk->put($path, $bytes, 'public');
        }

        return [
            'path' => $path,
            'url' => $disk->url($path),
            'bytes' => strlen($bytes),
            'original_bytes' => strlen($binary),
            'existed' => $existed,
        ];
    }

    public static function compress(string $binary, string $mime): array
    {
        $extension = self::EXTENSIONS[$mime];
        if (!in_array($extension, ['png', 'jpg', 'bmp'], true)) {
            return [$binary, $extension];
        }

        $image = @imagecreatefromstring($binary);
        if (!$image) {
            return [$binary, $extension];
        }
        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);

        $resized = false;
        if (imagesx($image) > self::MAX_WIDTH) {
            $image = self::resize($image, self::MAX_WIDTH);
            $resized = true;
        }

        $candidates = [];
        if (!$resized && $extension !== 'bmp') {
            $candidates[] = [$binary, $extension];
        }

        if ($extension === 'jpg') {
            $candidates[] = [self::encodeJpeg($image), 'jpg'];
        } else {
            $png = self::encodePng($image);
            $candidates[] = [$png, 'png'];

            $palette = self::toPalette($image);
            if ($palette) {
                $candidates[] = [$palette, 'png'];
            }

            if (!self::hasAlpha($image)) {
                $smallestPng = min(array_map(function ($candidate) {
                    return strlen($candidate[0]);
                }, $candidates));
                $jpeg = self::encodeJpeg($image);
                if (strlen($jpeg) < $smallestPng * self::JPEG_MIN_SAVING) {
                    $candidates[] = [$jpeg, 'jpg'];
                }
            }
        }

        imagedestroy($image);

        usort($candidates, function ($a, $b) {
            return strlen($a[0]) - strlen($b[0]);
        });

        return $candidates[0];
    }

    public static function decodeDataUri(string $base64): ?string
    {
        $binary = base64_decode($base64, true);

        return $binary === false || $binary === '' ? null : $binary;
    }

    private static function resize($image, int $width)
    {
        $height = max(1, (int) round(imagesy($image) * $width / imagesx($image)));
        $target = imagecreatetruecolor($width, $height);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
        imagedestroy($image);

        return $target;
    }

    private static function toPalette($image): ?string
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $palette = imagecreatetruecolor($width, $height);
        imagealphablending($palette, false);
        imagesavealpha($palette, true);
        imagecopy($palette, $image, 0, 0, 0, 0, $width, $height);
        imagetruecolortopalette($palette, false, 256);

        $step = max(1, (int) floor(sqrt(($width * $height) / self::SAMPLE_PIXELS)));
        $totalDiff = 0;
        $outliers = 0;
        $samples = 0;
        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                $original = imagecolorat($image, $x, $y);
                $quantized = imagecolorsforindex($palette, imagecolorat($palette, $x, $y));
                $diff = max(
                    abs((($original >> 16) & 0xFF) - $quantized['red']),
                    abs((($original >> 8) & 0xFF) - $quantized['green']),
                    abs(($original & 0xFF) - $quantized['blue']),
                    abs((($original >> 24) & 0x7F) - $quantized['alpha']) * 2
                );
                $totalDiff += $diff;
                if ($diff > self::PALETTE_OUTLIER_DIFF) {
                    $outliers++;
                }
                $samples++;
            }
        }

        $acceptable = $samples > 0
            && $totalDiff / $samples <= self::PALETTE_MAX_AVG_DIFF
            && $outliers / $samples <= self::PALETTE_MAX_OUTLIER_RATIO;

        $bytes = $acceptable ? self::encodePng($palette) : null;
        imagedestroy($palette);

        return $bytes;
    }

    private static function hasAlpha($image): bool
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $step = max(1, (int) floor(sqrt(($width * $height) / self::SAMPLE_PIXELS)));
        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function encodePng($image): string
    {
        ob_start();
        imagepng($image, null, 9, PNG_ALL_FILTERS);

        return ob_get_clean();
    }

    private static function encodeJpeg($image): string
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $flat = imagecreatetruecolor($width, $height);
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagealphablending($flat, true);
        imagecopy($flat, $image, 0, 0, 0, 0, $width, $height);

        ob_start();
        imagejpeg($flat, null, self::JPEG_QUALITY);
        imagedestroy($flat);

        return ob_get_clean();
    }
}
