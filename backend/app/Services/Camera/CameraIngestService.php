<?php

namespace App\Services\Camera;

use App\Jobs\AnalyzeCameraFrame;
use App\Models\AuditLog;
use App\Models\CameraFeed;
use App\Models\CameraFrame;
use App\Models\Incident;
use App\Models\IncidentMedia;
use App\Services\AnalysisPipeline;
use App\Services\ImageProcessor;
use App\Services\IncidentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * CCTV and feed ingestion. Frames come from admin registered, allowlisted https snapshot URLs or from admin uploads.
 * Every detection becomes an "AI detected, unverified" incident that goes to moderation. It is never auto verified.
 * It is visible in the public feed only when severity and confidence pass the configured thresholds.
 */
class CameraIngestService
{
    public function __construct(private ImageProcessor $img, private AnalysisPipeline $pipeline, private IncidentService $svc) {}

    /** Store an uploaded or fetched frame (metadata stripped, re encoded) and queue it for analysis. */
    public function ingest(CameraFeed $feed, UploadedFile $file): CameraFrame
    {
        $meta = $this->img->store($file);
        $media = IncidentMedia::create($meta + ['user_id' => $feed->created_by, 'metadata_stripped' => true]);
        $frame = $feed->frames()->create(['media_id' => $media->id, 'status' => 'queued']);
        AnalyzeCameraFrame::dispatch($frame->id);
        return $frame;
    }

    /** Fetch one snapshot from an allowlisted URL. Blocks private networks and redirects (SSRF guard). */
    public function poll(CameraFeed $feed): ?CameraFrame
    {
        $feed->forceFill(['last_polled_at' => now()])->save();
        try {
            if (! CameraFeed::urlAllowed($feed->snapshot_url)) throw new \RuntimeException('host_not_allowlisted');
            $host = parse_url($feed->snapshot_url, PHP_URL_HOST);
            $ips = gethostbynamel($host) ?: [];
            if (! $ips) throw new \RuntimeException('dns_failed');
            foreach ($ips as $ip) {
                if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) throw new \RuntimeException('private_address');
            }
            $res = Http::timeout(8)->withOptions(['allow_redirects' => false])->get($feed->snapshot_url);
            if (! $res->successful()) throw new \RuntimeException('http_'.$res->status());
            $body = $res->body();
            if (strlen($body) > config('rescuelens.camera.max_bytes')) throw new \RuntimeException('too_large');
            if (! str_starts_with((string) $res->header('Content-Type'), 'image/')) throw new \RuntimeException('not_an_image');
            $tmp = tempnam(sys_get_temp_dir(), 'rlcam');
            file_put_contents($tmp, $body);
            try {
                $frame = $this->ingest($feed, new UploadedFile($tmp, 'snapshot', null, null, true));
            } finally {
                @unlink($tmp);
            }
            $feed->forceFill(['last_error' => null])->save();
            return $frame;
        } catch (\Throwable $e) {
            // Store only a short code; never the URL response or image bytes.
            $feed->forceFill(['last_error' => substr($e instanceof \RuntimeException ? $e->getMessage() : 'fetch_failed', 0, 120)])->save();
            return null;
        }
    }

    /** Detection rules for whether an AI detected camera incident may appear in the public feed. */
    public static function feedEligible(?string $severity, ?float $confidence, bool $isScene): bool
    {
        return $isScene && in_array($severity, config('rescuelens.camera.feed_severities'), true)
            && $confidence !== null && $confidence >= config('rescuelens.camera.min_confidence');
    }

    public function analyze(CameraFrame $frame): CameraFrame
    {
        $feed = $frame->feed;
        $a = $this->pipeline->run($frame->media, $feed->created_by, 'en', null, null, $feed->country_code);
        if ($a->status !== 'completed') {
            $frame->update(['status' => 'failed', 'error' => $a->error_code]);
            return $frame;
        }
        $r = $a->result ?? [];
        $isScene = (bool) ($r['is_incident_scene'] ?? false);
        $category = $a->primary_hazard;
        if (! $isScene || ! in_array($category, Incident::CATEGORIES, true)) {
            $frame->update(['status' => 'no_incident']);
            return $frame;
        }
        $conf = $a->regions->max('confidence');
        $conf = $conf === null ? null : (float) $conf;

        // Dedupe: an open incident of the same category nearby absorbs this frame instead of creating a new one.
        if ($dup = $this->svc->duplicates($category, $feed->lat, $feed->lng)->first()) {
            $a->update(['incident_id' => $dup->id]);
            $frame->media->update(['incident_id' => $dup->id]);
            $frame->update(['status' => 'duplicate', 'incident_id' => $dup->id]);
            return $frame;
        }

        $eligible = self::feedEligible($a->severity, $conf, $isScene);
        $incident = DB::transaction(function () use ($feed, $frame, $a, $r, $category, $conf, $eligible) {
            $i = Incident::create([
                'user_id' => $feed->created_by, 'is_anonymous' => true,
                'title' => strip_tags(mb_substr($r['suggested_title_en'] ?? $r['suggested_title'] ?? 'Possible incident seen by camera', 0, 160)),
                'description' => strip_tags(mb_substr('AI detected, unverified. Camera: '.$feed->name.'. '.($r['summary_en'] ?? ''), 0, 2000)),
                'category' => $category, 'severity' => in_array($a->severity, Incident::SEVERITIES, true) ? $a->severity : 'moderate',
                'status' => 'reported', 'lat' => $feed->lat, 'lng' => $feed->lng,
                'public_lat' => Incident::roundPublic($feed->lat), 'public_lng' => Incident::roundPublic($feed->lng),
                'area_name' => $feed->area_name, 'ai_assisted' => true, 'occurred_at' => $frame->created_at ?? now(),
                'source' => 'camera', 'ai_unverified' => true, 'ai_confidence' => $conf, 'feed_eligible' => $eligible, 'camera_feed_id' => $feed->id,
            ]);
            $frame->media->update(['incident_id' => $i->id]);
            $a->update(['incident_id' => $i->id]);
            $i->history()->create(['actor_id' => null, 'from_status' => null, 'to_status' => 'reported',
                'note' => 'AI detected, unverified (camera feed). Sent to moderation. '.($eligible ? 'Shown in feed: severity and confidence passed thresholds.' : 'Hidden from feed until a moderator reviews it.')]);
            return $i;
        });
        $frame->update(['status' => 'incident', 'incident_id' => $incident->id]);
        AuditLog::record('incident.camera_detected', $incident, ['feed' => $feed->public_id, 'feed_eligible' => $eligible]);
        if ($eligible) $this->svc->notifyNearby($incident);
        return $frame;
    }
}
