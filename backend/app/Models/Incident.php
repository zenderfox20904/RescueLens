<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Incident extends Model
{
    public const CATEGORIES = ['road_accident', 'flood', 'fire', 'electrical', 'building_collapse', 'injury', 'other'];
    public const SEVERITIES = ['low', 'moderate', 'high', 'critical'];
    public const STATUSES = ['reported', 'corroborated', 'verified', 'disputed', 'resolved', 'expired', 'removed', 'withdrawn'];
    public const PUBLIC_STATUSES = ['reported', 'corroborated', 'verified', 'disputed', 'resolved', 'expired'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'is_demo' => 'boolean',
            'ai_assisted' => 'boolean',
            'lat' => 'float', 'lng' => 'float', 'public_lat' => 'float', 'public_lng' => 'float',
            'occurred_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public static function cacheVersion(): int
    {
        return (int) \Illuminate\Support\Facades\Cache::get('incidents:version', 1);
    }

    public static function bumpCache(): void
    {
        \Illuminate\Support\Facades\Cache::forever('incidents:version', static::cacheVersion() + 1);
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::bumpCache());
        static::deleted(fn () => static::bumpCache());
        static::creating(function (Incident $i) {
            $i->public_id ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function user() { return $this->belongsTo(User::class); }
    public function media() { return $this->hasMany(IncidentMedia::class); }
    public function analyses() { return $this->hasMany(AiAnalysis::class); }
    public function confirmations() { return $this->hasMany(IncidentConfirmation::class); }
    public function comments() { return $this->hasMany(IncidentComment::class); }
    public function history() { return $this->hasMany(IncidentStatusHistory::class); }
    public function mergedInto() { return $this->belongsTo(Incident::class, 'merged_into_id'); }

    public function scopePublic($q)
    {
        return $q->whereIn('status', self::PUBLIC_STATUSES)->whereNull('merged_into_id');
    }

    /** Bounding box prefilter then haversine in PHP is fine for MVP volumes; SQL haversine works on MySQL and MariaDB. */
    public function scopeNear($q, float $lat, float $lng, float $km)
    {
        $d = $km / 111.0;
        return $q->whereBetween('public_lat', [$lat - $d, $lat + $d])
            ->whereBetween('public_lng', [$lng - $d * 1.2, $lng + $d * 1.2]);
    }

    public function isStale(): bool
    {
        return in_array($this->status, ['reported', 'corroborated', 'disputed'])
            && $this->updated_at?->lt(now()->subHours(config('rescuelens.stale_hours')));
    }

    public static function roundPublic(float $v): float
    {
        return round($v, config('rescuelens.public_location_decimals'));
    }
}
