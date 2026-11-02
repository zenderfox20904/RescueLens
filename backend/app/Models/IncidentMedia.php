<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class IncidentMedia extends Model
{
    protected $table = 'incident_media';
    protected $guarded = ['id'];
    protected $hidden = ['path', 'video_path', 'disk', 'sha256'];

    protected static function booted(): void
    {
        static::creating(fn ($m) => $m->public_id ??= (string) Str::ulid());
    }

    public function signedUrl(int $minutes = 60): string
    {
        return URL::temporarySignedRoute('media.file', now()->addMinutes($minutes), ['media' => $this->public_id]);
    }

    public function videoUrl(int $minutes = 60): ?string
    {
        return $this->video_path ? URL::temporarySignedRoute('media.video', now()->addMinutes($minutes), ['media' => $this->public_id]) : null;
    }
}
