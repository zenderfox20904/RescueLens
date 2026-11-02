<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiAnalysis;
use App\Models\AuditLog;
use App\Models\IdempotencyKey;
use App\Models\Incident;
use App\Models\IncidentComment;
use App\Models\IncidentConfirmation;
use App\Models\IncidentMedia;
use App\Models\UserNotification;
use App\Services\IncidentService;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class IncidentController extends Controller
{
    public function __construct(private IncidentService $svc) {}

    private function base()
    {
        return Incident::public()->with(['user', 'media', 'analyses.regions', 'confirmations']);
    }

    public function index(Request $r)
    {
        $d = $r->validate([
            'category' => ['nullable', Rule::in(Incident::CATEGORIES)], 'status' => ['nullable', Rule::in(Incident::PUBLIC_STATUSES)],
            'severity' => ['nullable', Rule::in(Incident::SEVERITIES)],
            'lat' => 'nullable|numeric|between:-90,90', 'lng' => 'nullable|numeric|between:-180,180', 'radius_km' => 'nullable|numeric|between:1,100',
            'hours' => 'nullable|integer|between:1,720', 'mine' => 'nullable|boolean', 'per_page' => 'nullable|integer|between:1,50', 'cursor' => 'nullable|string|max:200',
        ]);
        $user = $r->user('sanctum');
        $q = ($d['mine'] ?? false) && $user
            ? Incident::where('user_id', $user->id)->with(['user', 'media', 'analyses.regions', 'confirmations'])
            : $this->base();
        $q->when($d['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->when($d['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($d['severity'] ?? null, fn ($q, $v) => $q->where('severity', $v))
            ->when($d['hours'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', now()->subHours($v)));
        if (isset($d['lat'], $d['lng'])) {
            $q->near($d['lat'], $d['lng'], $d['radius_km'] ?? 10);
        }
        $build = function () use ($q, $d, $user) {
            // Cursor pagination: O(1) per page regardless of depth, stable under new inserts.
            $page = $q->orderByDesc('id')->cursorPaginate($d['per_page'] ?? 20);
            return [
                'data' => collect($page->items())->map(fn ($i) => Present::incident($i, $user))->values(),
                'meta' => ['next_cursor' => $page->nextCursor()?->encode(), 'per_page' => $page->perPage(), 'server_time' => now()->toIso8601String()],
            ];
        };
        // Anonymous public feed is cached briefly; any incident write bumps the version key.
        if (! $user && empty($d['mine'])) {
            $key = 'feed:'.Incident::cacheVersion().':'.md5(json_encode($d));
            $out = \Illuminate\Support\Facades\Cache::remember($key, 15, $build);
            $out['data'] = \App\Services\I18n\ContentTranslation::applyAll(collect($out['data'])->all(), $r->input('lang'));
            return response()->json($out)->header('Cache-Control', 'private, max-age=10');
        }
        $out = $build();
        $out['data'] = \App\Services\I18n\ContentTranslation::applyAll($out['data']->all(), $r->input('lang') ?? $user?->locale);
        return $out;
    }

    /** Lightweight payload for map markers and the "stories" alert strip. */
    public function map(Request $r)
    {
        $d = $r->validate(['hours' => 'nullable|integer|between:1,720', 'category' => ['nullable', Rule::in(Incident::CATEGORIES)]]);
        $key = 'map:'.Incident::cacheVersion().':'.md5(json_encode($d));
        $tr = fn ($o) => ['data' => \App\Services\I18n\ContentTranslation::applyAll(collect($o['data'])->all(), $r->input('lang'))];
        return response()->json($tr(\Illuminate\Support\Facades\Cache::remember($key, 15, fn () => ['data' => Incident::public()->with('media')
            ->where('created_at', '>=', now()->subHours($d['hours'] ?? 168))
            ->when($d['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->latest()->limit(500)->get()
            ->map(fn ($i) => ['id' => $i->public_id, 'title' => $i->title, 'category' => $i->category, 'severity' => $i->severity,
                'status' => $i->status, 'is_stale' => $i->isStale(), 'is_demo' => $i->is_demo, 'lat' => $i->public_lat, 'lng' => $i->public_lng,
                'area_name' => $i->area_name, 'confirm' => $i->confirmations_count, 'created_at' => $i->created_at->toIso8601String(),
                'thumb' => $i->media->first()?->signedUrl()])->values()])))->header('Cache-Control', 'private, max-age=10');
    }

    public function show(Request $r, Incident $incident)
    {
        $user = $r->user('sanctum');
        $visible = (in_array($incident->status, Incident::PUBLIC_STATUSES) && $incident->feed_eligible) || ($user && ($user->id === $incident->user_id || $user->isModerator()));
        abort_unless($visible, 404);
        $incident->load(['user', 'media', 'analyses.regions', 'confirmations']);
        $one = \App\Services\I18n\ContentTranslation::applyAll([Present::incident($incident, $user, true)], $r->input('lang') ?? $user?->locale);
        $one = \App\Services\I18n\ContentTranslation::apply($one, $r->input('lang') ?? $user?->locale, 'description');
        return ['data' => $one[0]];
    }

    public function duplicates(Request $r)
    {
        $d = $r->validate(['category' => ['required', Rule::in(Incident::CATEGORIES)], 'lat' => 'required|numeric|between:-90,90', 'lng' => 'required|numeric|between:-180,180']);
        $ids = $this->svc->duplicates($d['category'], $d['lat'], $d['lng'])->pluck('id');
        return ['data' => $this->base()->whereIn('id', $ids)->get()->map(fn ($i) => Present::incident($i, $r->user()))];
    }

    public function store(Request $r)
    {
        $d = $r->validate([
            'title' => 'required|string|max:160', 'description' => 'nullable|string|max:2000',
            'category' => ['required', Rule::in(Incident::CATEGORIES)], 'severity' => ['required', Rule::in(Incident::SEVERITIES)],
            'lat' => 'required|numeric|between:-90,90', 'lng' => 'required|numeric|between:-180,180',
            'area_name' => 'nullable|string|max:120', 'address' => 'nullable|string|max:300', 'is_anonymous' => 'boolean',
            'media_ids' => 'array|max:4', 'media_ids.*' => 'string|size:26', 'analysis_id' => 'nullable|string|size:26',
            'consent_public' => 'accepted', 'auto_post' => 'boolean', 'occurred_at' => 'nullable|date|before_or_equal:now',
        ]);
        $key = $r->header('Idempotency-Key');
        $user = $r->user();
        $auto = (bool) ($d['auto_post'] ?? false);
        // Auto post only for users who opted in with explicit consent, and only from a completed AI analysis.
        abort_if($auto && (! $user->auto_post_enabled || empty($d['analysis_id'])), 403, 'Auto post is not enabled for this account');
        if ($key) {
            abort_unless(preg_match('/^[A-Za-z0-9_-]{8,80}$/', $key), 422, 'Invalid Idempotency-Key');
            $prev = IdempotencyKey::where('user_id', $user->id)->where('key', $key)->first();
            if ($prev?->incident_id) {
                $i = Incident::with(['user', 'media', 'analyses.regions', 'confirmations'])->find($prev->incident_id);
                return response()->json(['data' => Present::incident($i, $user, true), 'idempotent_replay' => true], 200);
            }
        }

        $incident = DB::transaction(function () use ($d, $user, $key, $auto) {
            $i = Incident::create([
                'user_id' => $user->id, 'title' => strip_tags($d['title']), 'description' => isset($d['description']) ? strip_tags($d['description']) : null,
                'category' => $d['category'], 'severity' => $d['severity'], 'status' => 'reported',
                'lat' => $d['lat'], 'lng' => $d['lng'], 'public_lat' => Incident::roundPublic($d['lat']), 'public_lng' => Incident::roundPublic($d['lng']),
                'area_name' => $d['area_name'] ?? null, 'address' => $d['address'] ?? null, 'is_anonymous' => $d['is_anonymous'] ?? false,
                'ai_assisted' => ! empty($d['analysis_id']), 'occurred_at' => $d['occurred_at'] ?? now(),
                'source' => $auto ? 'auto_post' : 'user', 'ai_unverified' => $auto,
            ]);
            if (! empty($d['media_ids'])) {
                IncidentMedia::whereIn('public_id', $d['media_ids'])->where('user_id', $user->id)->whereNull('incident_id')->update(['incident_id' => $i->id]);
            }
            if (! empty($d['analysis_id'])) {
                AiAnalysis::where('public_id', $d['analysis_id'])->where('user_id', $user->id)->update(['incident_id' => $i->id]);
            }
            $i->history()->create(['actor_id' => $user->id, 'from_status' => null, 'to_status' => 'reported', 'note' => $auto ? 'Auto posted from AI analysis (AI detected, unverified); reporter consented in settings' : 'Submitted by reporter after confirmation']);
            if ($key) IdempotencyKey::create(['user_id' => $user->id, 'key' => $key, 'incident_id' => $i->id]);
            return $i;
        });
        AuditLog::record('incident.create', $incident);
        // Auto posts wait out the undo window before anyone nearby is alerted.
        if ($auto) {
            \App\Jobs\NotifyNearby::dispatch($incident->id)->delay(now()->addSeconds(config('rescuelens.auto_post_undo_seconds')));
            $notified = 0;
        } else {
            $notified = $this->svc->notifyNearby($incident);
            app(\App\Services\DispatchService::class)->dispatch($incident);
        }
        app(\App\Services\Integrations\WebhookDispatcher::class)->dispatch('incident.created', $incident);
        $incident->load(['user', 'media', 'analyses.regions', 'confirmations']);
        return response()->json(['data' => Present::incident($incident, $user, true), 'nearby_notified' => $notified,
            'undo_seconds' => $auto ? config('rescuelens.auto_post_undo_seconds') : null], 201);
    }

    public function update(Request $r, Incident $incident)
    {
        abort_unless($incident->user_id === $r->user()->id, 403);
        abort_if(in_array($incident->status, ['removed', 'withdrawn']), 409, 'Report can no longer be edited');
        $d = $r->validate(['title' => 'sometimes|string|max:160', 'description' => 'nullable|string|max:2000', 'severity' => ['sometimes', Rule::in(Incident::SEVERITIES)]]);
        $incident->update(array_map(fn ($v) => is_string($v) ? strip_tags($v) : $v, $d));
        $incident->history()->create(['actor_id' => $r->user()->id, 'from_status' => $incident->status, 'to_status' => $incident->status, 'note' => 'Edited by reporter: '.implode(', ', array_keys($d))]);
        AuditLog::record('incident.update', $incident, ['fields' => array_keys($d)]);
        return $this->show($r, $incident);
    }

    public function withdraw(Request $r, Incident $incident)
    {
        abort_unless($incident->user_id === $r->user()->id, 403);
        $this->svc->setStatus($incident, 'withdrawn', $r->user(), 'Withdrawn by reporter');
        AuditLog::record('incident.withdraw', $incident);
        return response()->noContent();
    }

    public function react(Request $r, Incident $incident)
    {
        abort_unless(in_array($incident->status, Incident::PUBLIC_STATUSES), 404);
        $d = $r->validate(['type' => 'required|in:confirm,dispute,none', 'media_id' => 'nullable|string|size:26']);
        $u = $r->user();
        abort_if($incident->user_id === $u->id && $d['type'] !== 'none', 422, 'You cannot confirm your own report.');
        if ($d['type'] === 'none') {
            IncidentConfirmation::where('incident_id', $incident->id)->where('user_id', $u->id)->delete();
        } else {
            $existing = IncidentConfirmation::where('incident_id', $incident->id)->where('user_id', $u->id)->first();
            IncidentConfirmation::updateOrCreate(['incident_id' => $incident->id, 'user_id' => $u->id], ['type' => $d['type']]);
            if (! $existing && $d['type'] === 'confirm') {
                UserNotification::create(['user_id' => $incident->user_id, 'incident_id' => $incident->id, 'type' => 'confirm',
                    'title' => "{$u->name} also sees \"{$incident->title}\""]);
            }
            // Duplicate merge from reporter side: attach their photo as extra evidence.
            if (! empty($d['media_id'])) {
                IncidentMedia::where('public_id', $d['media_id'])->where('user_id', $u->id)->whereNull('incident_id')->update(['incident_id' => $incident->id]);
            }
        }
        $this->svc->recountReactions($incident, $u);
        $incident->touch();
        return $this->show($r, $incident->fresh());
    }

    public function comments(Incident $incident)
    {
        abort_unless(in_array($incident->status, Incident::PUBLIC_STATUSES), 404);
        $rows = $incident->comments()->with('user')->latest()->limit(100)->get()->reverse()->values()
            ->map(fn ($c) => ['id' => $c->id, 'body' => $c->body, 'author' => $c->user?->name, 'author_role' => $c->user?->role, 'created_at' => $c->created_at->toIso8601String()])->all();
        return ['data' => \App\Services\I18n\ContentTranslation::apply($rows, request()->input('lang') ?? request()->user('sanctum')?->locale, 'body')];
    }

    public function comment(Request $r, Incident $incident)
    {
        abort_unless(in_array($incident->status, Incident::PUBLIC_STATUSES), 404);
        $d = $r->validate(['body' => 'required|string|max:1000']);
        $c = IncidentComment::create(['incident_id' => $incident->id, 'user_id' => $r->user()->id, 'body' => strip_tags($d['body'])]);
        $incident->increment('comments_count');
        if ($incident->user_id !== $r->user()->id) {
            UserNotification::create(['user_id' => $incident->user_id, 'incident_id' => $incident->id, 'type' => 'comment', 'title' => "{$r->user()->name} commented on \"{$incident->title}\""]);
        }
        return response()->json(['data' => ['id' => $c->id, 'body' => $c->body, 'author' => $r->user()->name, 'author_role' => $r->user()->role, 'created_at' => $c->created_at->toIso8601String()]], 201);
    }
}
