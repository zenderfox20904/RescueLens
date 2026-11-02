<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Organization extends Model
{
    use SoftDeletes;

    public const TYPES = ['hospital', 'ambulance', 'fire_service', 'police', 'help_center', 'ngo', 'volunteer'];
    protected $guarded = ['id', 'verified_at'];
    protected $casts = ['verified_at' => 'datetime', 'lat' => 'float', 'lng' => 'float', 'is_demo' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(fn ($o) => $o->public_id ??= strtolower((string) Str::ulid()));
    }

    public function getRouteKeyName(): string { return 'public_id'; }
    public function user() { return $this->belongsTo(User::class); }

    /** Bounding box prefilter (uses org_geo_idx); callers refine with haversine. */
    public function scopeWithinBox($q, float $lat, float $lng, float $km)
    {
        $dLat = $km / 111.0;
        $dLng = $km / max(1.0, 111.0 * cos(deg2rad($lat)));
        return $q->whereBetween('lat', [$lat - $dLat, $lat + $dLat])->whereBetween('lng', [$lng - $dLng, $lng + $dLng]);
    }
}
