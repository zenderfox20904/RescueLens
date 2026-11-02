<?php

namespace App\Support;

use App\Models\AiAnalysis;
use App\Models\Incident;
use App\Models\User;

/** Explicit output shaping. Never exposes precise coordinates, emails or storage paths publicly. */
class Present
{
    public static function user(User $u): array
    {
        return ['id' => $u->public_id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role, 'locale' => $u->locale,
            'ai_consent' => $u->ai_consent, 'alert_radius_km' => $u->alert_radius_km,
            'alert_area_set' => $u->alert_lat !== null, 'is_demo' => $u->is_demo];
    }

    public static function analysis(AiAnalysis $a, bool $withMedia = true): array
    {
        return [
            'id' => $a->public_id, 'status' => $a->status, 'mode' => $a->mode,
            'mode_label' => $a->mode === 'demo' ? 'DEMO sample output. Live AI is not configured; this is not a real analysis of the photo.' : 'AI suggestion from Meta Model API. Verify before relying on it.',
            'provider' => $a->provider, 'vision_model' => $a->vision_model, 'segmentation_model' => $a->segmentation_model,
            'language' => $a->language, 'primary_hazard' => $a->primary_hazard, 'severity' => $a->severity,
            'requires_human_review' => $a->requires_human_review, 'error_code' => $a->error_code,
            'result' => $a->result, 'latency_ms' => $a->latency_ms, 'created_at' => $a->created_at?->toIso8601String(),
            'evidence_regions' => $a->regions->map(fn ($r) => ['label' => $r->label, 'source' => $r->source,
                'box' => [$r->x1, $r->y1, $r->x2, $r->y2], 'image_width' => $r->image_width, 'image_height' => $r->image_height,
                'confidence' => $r->confidence])->values(),
            'media' => $withMedia && $a->media ? ['id' => $a->media->public_id, 'url' => $a->media->signedUrl(), 'width' => $a->media->width, 'height' => $a->media->height] : null,
        ];
    }

    public static function incident(Incident $i, ?User $viewer = null, bool $detail = false): array
    {
        $mine = $viewer && $i->user_id === $viewer->id;
        $analysis = $i->relationLoaded('analyses') ? $i->analyses->sortByDesc('id')->first() : null;
        $out = [
            'id' => $i->public_id, 'title' => $i->title, 'description' => $i->description,
            'category' => $i->category, 'severity' => $i->severity, 'status' => $i->status, 'is_stale' => $i->isStale(),
            'is_demo' => $i->is_demo, 'ai_assisted' => $i->ai_assisted,
            'location' => ['lat' => $i->public_lat, 'lng' => $i->public_lng, 'area_name' => $i->area_name, 'precision' => 'approximate'],
            'reporter' => ['name' => $i->is_anonymous && ! $mine ? null : $i->user?->name, 'anonymous' => $i->is_anonymous, 'is_me' => $mine],
            'counts' => ['confirm' => $i->confirmations_count, 'dispute' => $i->disputes_count, 'comments' => $i->comments_count],
            'my_reaction' => $viewer ? $i->confirmations->firstWhere('user_id', $viewer->id)?->type : null,
            'media' => $i->media->map(fn ($m) => ['id' => $m->public_id, 'url' => $m->signedUrl(), 'width' => $m->width, 'height' => $m->height])->values(),
            'analysis' => $analysis ? [
                'mode' => $analysis->mode, 'hazards' => collect($analysis->result['possible_hazards'] ?? [])->pluck('type')->unique()->values(),
                'severity' => $analysis->severity, 'requires_human_review' => $analysis->requires_human_review,
                'evidence_regions' => $analysis->regions->map(fn ($r) => ['label' => $r->label, 'source' => $r->source, 'box' => [$r->x1, $r->y1, $r->x2, $r->y2], 'image_width' => $r->image_width, 'image_height' => $r->image_height])->values(),
            ] : null,
            'occurred_at' => $i->occurred_at?->toIso8601String(), 'created_at' => $i->created_at?->toIso8601String(),
            'updated_at' => $i->updated_at?->toIso8601String(), 'verified_at' => $i->verified_at?->toIso8601String(),
        ];
        if ($detail) {
            $out['analysis_detail'] = $analysis ? self::analysis($analysis, false) : null;
            $out['history'] = $i->history()->latest()->get()->map(fn ($h) => ['from' => $h->from_status, 'to' => $h->to_status, 'note' => $h->note,
                'by_role' => $h->actor?->role, 'at' => $h->created_at->toIso8601String()]);
            if ($viewer?->isModerator()) {
                $out['private'] = ['lat' => $i->lat, 'lng' => $i->lng, 'reporter_id' => $i->user?->public_id];
            }
        }
        return $out;
    }
}
