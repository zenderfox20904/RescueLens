<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CameraFeed extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_polled_at' => 'datetime', 'lat' => 'float', 'lng' => 'float'];
    }

    protected static function booted(): void
    {
        static::creating(fn ($f) => $f->public_id ??= (string) Str::ulid());
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function frames()
    {
        return $this->hasMany(CameraFrame::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Host allowlist check used at registration and again before every fetch. */
    public static function urlAllowed(?string $url): bool
    {
        if (! $url) return false;
        $p = parse_url($url);
        if (($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['pass'])) return false;
        return in_array(strtolower($p['host']), array_map('strtolower', config('rescuelens.camera.allowed_hosts')), true);
    }
}
