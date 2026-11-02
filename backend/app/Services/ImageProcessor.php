<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validates magic bytes and dimensions, then re-encodes with GD. Re-encoding drops all EXIF/GPS metadata.
 * Files are stored on the private local disk with random names, never in the public web root.
 */
class ImageProcessor
{
    private const SIGNATURES = ["\xFF\xD8\xFF" => 'image/jpeg', "\x89PNG\r\n\x1a\n" => 'image/png', 'RIFF' => 'image/webp'];

    public function store(UploadedFile $file): array
    {
        $head = file_get_contents($file->getRealPath(), false, null, 0, 12);
        $mime = null;
        foreach (self::SIGNATURES as $sig => $m) {
            if (str_starts_with($head, $sig) && ($m !== 'image/webp' || substr($head, 8, 4) === 'WEBP')) $mime = $m;
        }
        if (! $mime) $this->fail('Unsupported image. Use JPEG, PNG or WebP.');

        $info = @getimagesize($file->getRealPath());
        if (! $info || $info[0] < 64 || $info[1] < 64) $this->fail('Image is too small or unreadable.');
        if ($info[0] * $info[1] > config('rescuelens.max_image_pixels')) $this->fail('Image dimensions are too large.');

        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file->getRealPath()),
            'image/png' => @imagecreatefrompng($file->getRealPath()),
            'image/webp' => @imagecreatefromwebp($file->getRealPath()),
        };
        if (! $src) $this->fail('Image could not be decoded.');

        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $o = (@exif_read_data($file->getRealPath())['Orientation'] ?? 1);
            $src = match ($o) { 3 => imagerotate($src, 180, 0), 6 => imagerotate($src, -90, 0), 8 => imagerotate($src, 90, 0), default => $src };
        }
        $w = imagesx($src); $h = imagesy($src);
        $max = config('rescuelens.analysis_max_edge');
        if (max($w, $h) > $max) {
            $scale = $max / max($w, $h);
            $src = imagescale($src, (int) round($w * $scale), (int) round($h * $scale));
            $w = imagesx($src); $h = imagesy($src);
        }
        ob_start();
        imagejpeg($src, null, 85);
        $bytes = ob_get_clean();
        imagedestroy($src);

        $path = 'incident-media/'.now()->format('Y/m').'/'.Str::random(40).'.jpg';
        Storage::disk('local')->put($path, $bytes);

        return ['path' => $path, 'mime' => 'image/jpeg', 'width' => $w, 'height' => $h, 'size_bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes)];
    }

    private function fail(string $msg): never
    {
        throw ValidationException::withMessages(['image' => $msg]);
    }
}
