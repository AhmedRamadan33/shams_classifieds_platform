<?php

declare(strict_types=1);

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Re-encodes an uploaded image with GD before it is stored.
 *
 * This proves the file really is a decodable image (not just a renamed file), applies the EXIF
 * orientation, and drops every metadata block (EXIF/GPS, ICC, comments) from the stored original.
 * The generated conversions are re-encoded again by the media library.
 */
final class ImageSanitizer
{
    private const TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * @return array{path: string, extension: string} a temporary file the caller should hand to the media library
     *
     * @throws InvalidArgumentException when the upload is not a supported, decodable image
     */
    public function sanitize(UploadedFile $file): array
    {
        $realPath = $file->getRealPath();
        $mime = $realPath ? (string) mime_content_type($realPath) : '';

        if (! isset(self::TYPES[$mime])) {
            throw new InvalidArgumentException('Unsupported image type.');
        }

        $image = @imagecreatefromstring((string) file_get_contents($realPath));

        if (! $image instanceof GdImage) {
            throw new InvalidArgumentException('The file is not a valid image.');
        }

        if ($mime === 'image/jpeg') {
            $image = $this->applyOrientation($image, $realPath);
        }

        $extension = self::TYPES[$mime];
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'shams-'.Str::random(24).'.'.$extension;

        $written = match ($extension) {
            'jpg' => imagejpeg($image, $path, 90),
            'png' => $this->writePng($image, $path),
            'webp' => $this->writeWebp($image, $path),
        };

        if (! $written) {
            throw new InvalidArgumentException('The image could not be processed.');
        }

        return ['path' => $path, 'extension' => $extension];
    }

    private function writePng(GdImage $image, string $path): bool
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return imagepng($image, $path, 6);
    }

    private function writeWebp(GdImage $image, string $path): bool
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return imagewebp($image, $path, 88);
    }

    /**
     * Rotate the pixels according to the EXIF orientation flag so the image looks right once
     * the flag itself is gone.
     */
    private function applyOrientation(GdImage $image, string $path): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $angle = match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        return $rotated instanceof GdImage ? $rotated : $image;
    }
}
