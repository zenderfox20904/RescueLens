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
        return ['id' => $u->public_id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role, 'account_type' => $u->account_type ?? 'citizen', 'organization' => ($o = \App\Models\Organization::where('user_id', $u->id)->first()) ? \App\Services\DispatchService::presentOrg($o, true) : null, 'locale' => $u->locale, 'country_code' => $u->country_code ?? 'BD', 'distance_unit' => $u->distance_unit ?? 'km', 'timezone' => $u->timezone,
            'ai_consent' => $u->ai_consent, 'auto_post_enabled' => (bool) $u->auto_post_enabled, 'alert_radius_km' => $u->alert_radius_km,
            'alert_area_set' => $u->alert_lat !== null, 'alert_lat' => $u->alert_lat !== null ? round((float) $u->alert_lat, 4) : null, 'alert_lng' => $u->alert_lng !== null ? round((float) $u->alert_lng, 4) : null, 'is_demo' => $u->is_demo, 'phone' => $u->phone, 'bio' => $u->bio, 'avatar_url' => $u->avatar_path ? \Illuminate\Support\Facades\URL::temporarySignedRoute('avatar.file', now()->addHours(6), ['user' => $u->public_id]) : null, 'created_at' => $u->created_at?->toIso8601String(), 'theme' => $u->theme ?? 'system', 'notify_prefs' => $u->notifyPrefs()];
    }

    public static function analysis(AiAnalysis $a, bool $withMedia = true): array
    {
        // The local dev backend is an internal detail; users only ever see Meta AI labels.
        $dev = $a->mode === 'dev';
        return [
            'id' => $a->public_id, 'status' => $a->status, 'mode' => $dev ? 'live' : $a->mode,
            'mode_label' => $a->mode === 'demo' ? 'DEMO sample output. Live AI is not configured; this is not a real analysis of the photo.' : 'Meta AI suggestion. Verify before relying on it.',
            'provider' => 'meta', 'vision_model' => $dev ? config('rescuelens.meta.vision_model') : $a->vision_model,
            'segmentation_model' => $dev ? config('rescuelens.meta.segmentation_model') : $a->segmentation_model,
            'language' => $a->language, 'primary_hazard' => $a->primary_hazard, 'severity' => $a->severity,
            'requires_human_review' => $a->requires_human_review, 'error_code' => $a->error_code,
            'assessment' => self::assessment($a),
            'result' => $a->result, 'latency_ms' => $a->latency_ms, 'created_at' => $a->created_at?->toIso8601String(),
            'evidence_regions' => $a->regions->map(fn ($r) => ['label' => $r->label, 'source' => $r->source,
                'box' => [$r->x1, $r->y1, $r->x2, $r->y2], 'image_width' => $r->image_width, 'image_height' => $r->image_height,
                'confidence' => $r->confidence])->values(),
            'media' => $withMedia && $a->media ? ['id' => $a->media->public_id, 'url' => $a->media->signedUrl(), 'width' => $a->media->width, 'height' => $a->media->height] : null,
        ];
    }

    /** Compact, UI ready view of an analysis: category, severity, confidence, reasons and who should respond. */
    public static function assessment(AiAnalysis $a): ?array
    {
        $r = $a->result ?? [];
        if (! $r) return null;
        $haz = collect($r['possible_hazards'] ?? []);
        $weight = ['clearly_visible' => 0.9, 'likely' => 0.7, 'possible' => 0.45];
        $type = $a->primary_hazard ?? ($haz->first()['type'] ?? 'other');
        $type = $type === 'smoke' ? 'fire' : $type;
        $cat = in_array($type, Incident::CATEGORIES, true) ? $type : 'other';
        $isIncident = (bool) ($r['is_incident_scene'] ?? true);
        $conf = $haz->isEmpty() ? 0.4 : round($haz->map(fn ($h) => $weight[$h['likelihood'] ?? 'possible'] ?? 0.45)->max(), 2);
        $reasons = array_values(array_filter(array_merge([$r['severity_reason'] ?? null],
            $haz->take(3)->map(fn ($h) => $h['evidence'] ?? null)->all())));
        return ['is_incident' => $isIncident, 'category' => $cat, 'severity' => $a->severity ?? 'moderate', 'confidence' => $isIncident ? $conf : round(1 - $conf / 2, 2),
            'summary' => $r['summary'] ?? null, 'suggested_title' => $r['suggested_title'] ?? null, 'reasons' => array_slice($reasons, 0, 4),
            'responders' => $isIncident ? \App\Services\DispatchService::responderTypes($cat, $a->severity ?? 'moderate') : [],
            'precautions' => array_slice($r['recommended_precautions'] ?? [], 0, 3)];
    }

    public static function incident(Incident $i, ?User $viewer = null, bool $detail = false): array
    {
        $mine = $viewer && $i->user_id === $viewer->id;
        $analysis = $i->relationLoaded('analyses') ? $i->analyses->sortByDesc('id')->first() : null;
        $out = [
            'id' => $i->public_id, 'title' => $i->title, 'description' => $i->description,
            'category' => $i->category, 'severity' => $i->severity, 'status' => $i->status, 'is_stale' => $i->isStale(),
            'is_demo' => $i->is_demo, 'ai_assisted' => $i->ai_assisted, 'source' => $i->source ?? 'user', 'ai_unverified' => (bool) $i->ai_unverified,
            'location' => ['lat' => $i->public_lat, 'lng' => $i->public_lng, 'area_name' => $i->area_name, 'precision' => 'approximate'],
            'reporter' => ['id' => $i->is_anonymous && ! $mine ? null : $i->user?->public_id, 'avatar' => $i->is_anonymous && ! $mine ? null : self::avatar($i->user), 'name' => $i->is_anonymous && ! $mine ? null : $i->user?->name, 'anonymous' => $i->is_anonymous, 'is_me' => $mine],
            'counts' => ['confirm' => $i->confirmations_count, 'dispute' => $i->disputes_count, 'comments' => $i->comments_count],
            'my_reaction' => $viewer ? $i->confirmations->firstWhere('user_id', $viewer->id)?->type : null,
            'media' => $i->media->map(fn ($m) => ['id' => $m->public_id, 'url' => $m->signedUrl(), 'mime' => $m->mime, 'width' => $m->width, 'height' => $m->height])->values(),
            'analysis' => $analysis ? [
                'mode' => $analysis->mode, 'hazards' => collect($analysis->result['possible_hazards'] ?? [])->pluck('type')->unique()->values(),
                'severity' => $analysis->severity, 'requires_human_review' => $analysis->requires_human_review,
                'evidence_regions' => $analysis->regions->map(fn ($r) => ['label' => $r->label, 'source' => $r->source, 'box' => [$r->x1, $r->y1, $r->x2, $r->y2], 'image_width' => $r->image_width, 'image_height' => $r->image_height])->values(),
            ] : null,
            'occurred_at' => $i->occurred_at?->toIso8601String(), 'created_at' => $i->created_at?->toIso8601String(),
            'is_mine' => $mine, 'can_manage' => $mine || ($viewer?->isModerator() ?? false), 'edited_at' => $i->edited_at?->toIso8601String(),
            'deleted_at' => $i->deleted_at?->toIso8601String(),
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

    public static function avatar(?User $u): ?string
    {
        return $u?->avatar_path ? \Illuminate\Support\Facades\URL::temporarySignedRoute('avatar.file', now()->addHours(6), ['user' => $u->id]) : null;
    }

    public static function comment(\App\Models\IncidentComment $c, ?User $viewer, array $liked = []): array
    {
        $mine = $viewer && $viewer->id === $c->user_id;
        return ['id' => $c->id, 'parent_id' => $c->parent_id, 'body' => $c->body, 'author' => $c->user?->name, 'author_role' => $c->user?->role,
            'author_avatar' => self::avatar($c->user), 'is_mine' => $mine, 'can_manage' => $mine || ($viewer?->isModerator() ?? false),
            'likes' => (int) $c->likes_count, 'liked' => in_array($c->id, $liked, true),
            'edited_at' => $c->edited_at?->toIso8601String(), 'created_at' => $c->created_at->toIso8601String()];
    }
}
