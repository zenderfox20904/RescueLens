<?php

namespace App\Services;

use App\Models\Dispatch;
use App\Models\Incident;
use App\Models\Organization;
use App\Models\UserNotification;

/**
 * Maps an incident (category and severity, usually set by AI analysis and confirmed by the reporter)
 * to responder types, then alerts verified organizations whose coverage radius includes the incident.
 * It never places phone calls; responders and citizens tap to call.
 */
class DispatchService
{
    public const MAP = [
        'fire' => ['fire_service', 'ambulance', 'hospital'],
        'wildfire' => ['fire_service', 'help_center', 'volunteer'],
        'road_accident' => ['ambulance', 'hospital', 'police'],
        'injury' => ['ambulance', 'hospital'],
        'building_collapse' => ['fire_service', 'ambulance', 'hospital', 'police'],
        'electrical' => ['fire_service'],
        'flood' => ['help_center', 'ngo', 'volunteer', 'fire_service'],
        'crowd_stampede' => ['police', 'ambulance', 'hospital'],
        'landslide' => ['fire_service', 'ambulance', 'help_center'],
        'earthquake' => ['fire_service', 'ambulance', 'hospital', 'help_center'],
        'tsunami' => ['help_center', 'police', 'hospital'],
        'cyclone_storm' => ['help_center', 'ngo', 'volunteer'],
        'snow_ice' => ['help_center', 'police'],
        'other' => ['police'],
    ];

    public static function responderTypes(string $category, string $severity): array
    {
        $t = self::MAP[$category] ?? ['police'];
        if (in_array($severity, ['high', 'critical'], true) && ! in_array('ambulance', $t, true) && in_array($category, ['flood', 'electrical', 'landslide', 'cyclone_storm'], true)) $t[] = 'ambulance';
        if ($severity === 'low') $t = array_slice($t, 0, 1);
        return $t;
    }

    /** Verified organizations near a point, nearest first. Bounding box on an index, then haversine. */
    public static function nearby(float $lat, float $lng, float $km, ?array $types = null, bool $respectCoverage = false, int $limit = 30)
    {
        return Organization::query()->whereNotNull('verified_at')
            ->when($types, fn ($q) => $q->whereIn('type', $types))
            ->withinBox($lat, $lng, $respectCoverage ? max($km, 100) : $km)
            ->limit(500)->get()
            ->map(function ($o) use ($lat, $lng) { $o->distance_km = round(IncidentService::distanceKm($lat, $lng, $o->lat, $o->lng), 2); return $o; })
            ->filter(fn ($o) => $respectCoverage ? $o->distance_km <= min($o->coverage_km, $km) : $o->distance_km <= $km)
            ->sortBy('distance_km')->take($limit)->values();
    }

    public function dispatch(Incident $i): int
    {
        $types = self::responderTypes($i->category, $i->severity);
        $orgs = self::nearby((float) $i->lat, (float) $i->lng, 50, $types, true)
            ->groupBy('type')->flatMap(fn ($g) => $g->take(3));
        $n = 0;
        foreach ($orgs as $o) {
            $d = Dispatch::firstOrCreate(['incident_id' => $i->id, 'organization_id' => $o->id],
                ['status' => 'alerted', 'distance_km' => $o->distance_km, 'reason' => "{$i->category} ({$i->severity}) needs ".str_replace('_', ' ', $o->type)]);
            if ($d->wasRecentlyCreated && $o->user_id) {
                UserNotification::create(['user_id' => $o->user_id, 'incident_id' => $i->id, 'type' => 'dispatch',
                    'title' => 'Response request: '.strtoupper($i->severity).' '.$i->title, 'body' => sprintf('About %.1f km from %s. Community report, confirm on scene.', $o->distance_km, $o->name)]);
            }
            $n += $d->wasRecentlyCreated ? 1 : 0;
        }
        return $n;
    }

    public static function present(Dispatch $d): array
    {
        $o = $d->organization;
        return ['id' => $d->id, 'status' => $d->status, 'distance_km' => $d->distance_km, 'reason' => $d->reason, 'updated_at' => $d->updated_at?->toIso8601String(),
            'organization' => $o ? ['id' => $o->public_id, 'name' => $o->name, 'type' => $o->type, 'phone' => $o->phone, 'verified' => $o->verified_at !== null] : null];
    }

    public static function presentOrg(Organization $o, bool $withPrivate = false): array
    {
        return array_filter(['id' => $o->public_id, 'type' => $o->type, 'name' => $o->name, 'phone' => $o->phone, 'lat' => $o->lat, 'lng' => $o->lng,
            'address' => $o->address, 'country_code' => $o->country_code, 'coverage_km' => $o->coverage_km, 'service_hours' => $o->service_hours,
            'verified' => $o->verified_at !== null, 'distance_km' => $o->distance_km ?? null, 'source' => 'rescuelens',
            'owner' => $withPrivate ? $o->user?->email : null, 'is_demo' => $o->is_demo], fn ($v) => $v !== null);
    }
}
