<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

/**
 * Short incident videos. ffmpeg rewrites the container without any metadata (GPS, device, time),
 * and grabs one frame as the poster. The poster goes through ImageProcessor like a photo, so
 * Meta AI analyzes the same evidence people see. The original upload is never kept.
 */
class VideoProcessor
{
    public function __construct(private ImageProcessor $img) {}

    public static function available(): bool
    {
        return is_executable(self::bin());
    }

    private static function bin(): string
    {
        foreach ([config('rescuelens.ffmpeg_bin'), '/opt/homebrew/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/usr/bin/ffmpeg'] as $b) if ($b && is_executable($b)) return $b;
        return '';
    }

    /** @return array{image: array, video_path: string, video_seconds: ?int} */
    public function store(UploadedFile $file): array
    {
        if (! self::available()) throw ValidationException::withMessages(['video' => 'Video upload is not available on this server yet. Please add a photo.']);
        $tmp = sys_get_temp_dir().'/rl_'.Str::random(16);
        $clean = "$tmp.mp4"; $poster = "$tmp.jpg";
        try {
            $p = new Process([self::bin(), '-v', 'error', '-y', '-i', $file->getRealPath(), '-t', (string) config('rescuelens.video_max_seconds'),
                '-map', '0:v:0', '-map', '0:a:0?', '-map_metadata', '-1', '-map_chapters', '-1', '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '28',
                '-vf', 'scale=trunc(min(1280\,iw)/2)*2:-2', '-c:a', 'aac', '-movflags', '+faststart', $clean]);
            $p->setTimeout(120)->run();
            if (! $p->isSuccessful() || ! is_file($clean)) throw ValidationException::withMessages(['video' => 'Video could not be read. Use MP4, MOV or WebM.']);
            $probe = new Process([self::bin(), '-v', 'error', '-y', '-ss', '1', '-i', $clean, '-frames:v', '1', '-q:v', '3', $poster]);
            $probe->setTimeout(60)->run();
            if (! is_file($poster)) (new Process([self::bin(), '-v', 'error', '-y', '-i', $clean, '-frames:v', '1', $poster]))->run();
            $image = $this->img->store(new UploadedFile($poster, 'poster.jpg', 'image/jpeg', null, true));
            $path = 'videos/'.Str::ulid().'.mp4';
            Storage::disk('local')->put($path, file_get_contents($clean));
            return ['image' => $image, 'video_path' => $path, 'video_seconds' => null];
        } finally {
            @unlink($clean); @unlink($poster);
        }
    }
}
