<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Dispatch;
use App\Models\Incident;
use App\Models\Organization;
use App\Services\DispatchService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    /** Verified responders near a point, filtered by radius (bounding box plus haversine). */
    public function nearby(Request $r)
    {
        $d = $r->validate(['lat' => 'required|numeric|between:-90,90', 'lng' => 'required|numeric|between:-180,180',
            'radius_km' => 'nullable|numeric|between:1,100', 'types' => 'nullable|string|max:120']);
        $types = isset($d['types']) ? array_values(array_intersect(explode(',', $d['types']), Organization::TYPES)) : null;
        $rows = DispatchService::nearby($d['lat'], $d['lng'], (float) ($d['radius_km'] ?? 10), $types ?: null);
        return ['data' => $rows->map(fn ($o) => DispatchService::presentOrg($o))];
    }

    public function forIncident(Incident $incident)
    {
        $rows = Dispatch::with('organization')->where('incident_id', $incident->id)->orderBy('distance_km')->get();
        return ['data' => $rows->map(fn ($d) => DispatchService::present($d)),
            'responder_types' => DispatchService::responderTypes($incident->category, $incident->severity)];
    }

    public function mine(Request $r)
    {
        $org = Organization::where('user_id', $r->user()->id)->first();
        if (! $org) return ['organization' => null, 'data' => []];
        $rows = Dispatch::with(['incident', 'organization'])->where('organization_id', $org->id)->latest('id')->limit(50)->get();
        return ['organization' => DispatchService::presentOrg($org, true), 'data' => $rows->map(fn ($d) => DispatchService::present($d) + [
            'incident' => $d->incident ? ['id' => $d->incident->public_id, 'title' => $d->incident->title, 'category' => $d->incident->category,
                'severity' => $d->incident->severity, 'area_name' => $d->incident->area_name, 'lat' => (float) $d->incident->lat, 'lng' => (float) $d->incident->lng] : null])];
    }

    public function setStatus(Request $r, Dispatch $dispatch)
    {
        abort_unless($dispatch->organization?->user_id === $r->user()->id, 403);
        $d = $r->validate(['status' => ['required', Rule::in(['accepted', 'en_route', 'on_scene', 'resolved', 'declined'])]]);
        $dispatch->update(['status' => $d['status']]);
        AuditLog::record('dispatch.status', $dispatch->incident, ['status' => $d['status'], 'org' => $dispatch->organization->public_id]);
        return ['data' => DispatchService::present($dispatch->load('organization'))];
    }

    public function updateMine(Request $r)
    {
        $org = Organization::where('user_id', $r->user()->id)->firstOrFail();
        $org->update($r->validate(self::rules(true)));
        return ['data' => DispatchService::presentOrg($org, true)];
    }

    public static function rules(bool $partial = false): array
    {
        $s = $partial ? 'sometimes' : 'required';
        return ['type' => [$s, Rule::in(Organization::TYPES)], 'name' => "$s|string|max:160", 'phone' => [$s, 'regex:/^[0-9+ ()]{3,30}$/'],
            'lat' => "$s|numeric|between:-90,90", 'lng' => "$s|numeric|between:-180,180", 'address' => 'nullable|string|max:300',
            'country_code' => ['nullable', 'regex:/^[A-Za-z]{2}$/'], 'coverage_km' => 'nullable|integer|between:1,100', 'service_hours' => 'nullable|string|max:80'];
    }
}
