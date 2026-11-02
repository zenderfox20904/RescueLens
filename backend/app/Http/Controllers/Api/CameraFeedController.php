<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\PollCameraFeed;
use App\Models\AuditLog;
use App\Models\CameraFeed;
use App\Services\Camera\CameraIngestService;
use Illuminate\Http\Request;

/** Admin only management of camera feeds. Snapshot URLs never leave the admin API. */
class CameraFeedController extends Controller
{
    private static function present(CameraFeed $f): array
    {
        return ['id' => $f->public_id, 'name' => $f->name, 'kind' => $f->kind, 'snapshot_host' => $f->snapshot_url ? parse_url($f->snapshot_url, PHP_URL_HOST) : null,
            'lat' => $f->lat, 'lng' => $f->lng, 'area_name' => $f->area_name, 'country_code' => $f->country_code, 'interval_seconds' => $f->interval_seconds,
            'is_active' => $f->is_active, 'last_polled_at' => $f->last_polled_at?->toIso8601String(), 'last_error' => $f->last_error,
            'frames' => $f->frames_count ?? null, 'detections' => $f->detections_count ?? null];
    }

    public function index()
    {
        return ['data' => CameraFeed::withCount(['frames', 'frames as detections_count' => fn ($q) => $q->where('status', 'incident')])
            ->latest()->get()->map(fn ($f) => self::present($f)),
            'allowed_hosts' => config('rescuelens.camera.allowed_hosts'),
            'rules' => ['min_confidence' => config('rescuelens.camera.min_confidence'), 'feed_severities' => config('rescuelens.camera.feed_severities')]];
    }

    public function store(Request $r)
    {
        $d = $r->validate([
            'name' => 'required|string|max:120', 'kind' => 'required|in:snapshot_url,upload',
            'snapshot_url' => 'required_if:kind,snapshot_url|nullable|url|max:500',
            'lat' => 'required|numeric|between:-90,90', 'lng' => 'required|numeric|between:-180,180',
            'area_name' => 'nullable|string|max:120', 'country_code' => 'nullable|string|size:2|alpha',
            'interval_seconds' => 'nullable|integer|between:60,86400',
        ]);
        if ($d['kind'] === 'snapshot_url') {
            abort_unless(CameraFeed::urlAllowed($d['snapshot_url']), 422, 'Snapshot URL must be https on an allowlisted host (CAMERA_ALLOWED_HOSTS).');
        } else {
            $d['snapshot_url'] = null;
        }
        $d['country_code'] = strtoupper($d['country_code'] ?? 'BD');
        $f = CameraFeed::create($d + ['created_by' => $r->user()->id]);
        AuditLog::record('camera.create', $f, ['host' => $f->snapshot_url ? parse_url($f->snapshot_url, PHP_URL_HOST) : null]);
        return response()->json(['data' => self::present($f)], 201);
    }

    public function update(Request $r, CameraFeed $feed)
    {
        $d = $r->validate(['is_active' => 'sometimes|boolean', 'name' => 'sometimes|string|max:120', 'interval_seconds' => 'sometimes|integer|between:60,86400']);
        $feed->update($d);
        AuditLog::record('camera.update', $feed, ['fields' => array_keys($d)]);
        return ['data' => self::present($feed)];
    }

    public function destroy(CameraFeed $feed)
    {
        AuditLog::record('camera.delete', $feed);
        $feed->delete();
        return response()->noContent();
    }

    public function poll(CameraFeed $feed)
    {
        abort_unless($feed->kind === 'snapshot_url' && $feed->is_active, 422, 'Feed is not an active snapshot feed');
        PollCameraFeed::dispatch($feed->id);
        return response()->json(['queued' => true], 202);
    }

    public function upload(Request $r, CameraFeed $feed, CameraIngestService $svc)
    {
        $r->validate(['image' => 'required|file|image|max:8192']);
        abort_unless($feed->is_active, 422, 'Feed is paused');
        $frame = $svc->ingest($feed, $r->file('image'));
        return response()->json(['data' => ['frame_id' => $frame->id, 'status' => $frame->fresh()->status, 'incident_id' => $frame->fresh()->incident?->public_id]], 202);
    }
}
