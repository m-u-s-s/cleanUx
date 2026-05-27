<?php

namespace App\Services\Media;

use RuntimeException;

/**
 * Resize and compress images using the GD extension (bundled with PHP).
 * Supports JPEG, PNG, and WebP inputs; always outputs JPEG.
 */
class ImageOptimizer
{
    /**
     * Optimize an image file in-place (overwrites the original).
     *
     * @param  string  $path      Absolute filesystem path to the image.
     * @param  int     $maxWidth  Maximum output width in pixels (aspect ratio preserved).
     * @param  int     $quality   JPEG quality 1–100.
     * @return string  The same $path, for fluent chaining.
     *
     * @throws RuntimeException  When GD is not loaded or the file cannot be read.
     */
    public function optimize(string $path, int $maxWidth = 1200, int $quality = 80): string
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('GD extension is required for image optimization.');
        }

        $src = $this->loadImage($path);

        $origW = imagesx($src);
        $origH = imagesy($src);

        [$newW, $newH] = $this->scaledDimensions($origW, $origH, $maxWidth);

        $dst = imagecreatetruecolor($newW, $newH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        imagejpeg($dst, $path, $quality);

        imagedestroy($src);
        imagedestroy($dst);

        return $path;
    }

    /** @return array{int, int} */
    private function scaledDimensions(int $w, int $h, int $maxW): array
    {
        if ($w <= $maxW) {
            return [$w, $h];
        }
        $ratio = $maxW / $w;
        return [$maxW, (int) round($h * $ratio)];
    }

    /** @return \GdImage */
    private function loadImage(string $path): \GdImage
    {
        $mime = mime_content_type($path);

        $image = match ($mime) {
            'image/jpeg', 'image/jpg' => imagecreatefromjpeg($path),
            'image/png'               => imagecreatefrompng($path),
            'image/webp'              => imagecreatefromwebp($path),
            default                   => throw new RuntimeException("Unsupported image type: {$mime}"),
        };

        if ($image === false) {
            throw new RuntimeException("Failed to load image: {$path}");
        }

        return $image;
    }
}
