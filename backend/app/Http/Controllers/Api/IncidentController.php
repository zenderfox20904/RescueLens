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
            'hours' => 'nullable|integer|between:1,720', 'mine' => 'nullable|boolean', 'user' => 'nullable|string|max:40', 'per_page' => 'nullable|integer|between:1,50', 'cursor' => 'nullable|string|max:200',
        ]);
        $user = $r->user('sanctum');
        $q = ($d['mine'] ?? false) && $user
            ? Incident::where('user_id', $user->id)->with(['user', 'media', 'analyses.regions', 'confirmations'])
            : $this->base();
        if (! empty($d['user'])) {
            $d['user'] = \App\Models\User::where('public_id', $d['user'])->value('id') ?? 0;
            // Profile timeline: own profile shows everything incl. anonymous; others see only public, named posts.
            $q = $user && $user->id === $d['user']
                ? Incident::where('user_id', $user->id)->with(['user', 'media', 'analyses.regions', 'confirmations'])
                : $this->base()->where('user_id', $d['user'])->where('is_anonymous', false);
        }
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
            $out = \Illuminate\Support\Facades\Cache::remember($key, 15, fn () => json_decode(json_encode($build()), true));
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
        return response()->json($tr(\Illuminate\Support\Facades\Cache::remember($key, 15, fn () => json_decode(json_encode(['data' => Incident::public()->with('media')
            ->where('created_at', '>=', now()->subHours($d['hours'] ?? 168))
            ->when($d['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->latest()->limit(500)->get()
            ->map(fn ($i) => ['id' => $i->public_id, 'title' => $i->title, 'category' => $i->category, 'severity' => $i->severity,
                'status' => $i->status, 'is_stale' => $i->isStale(), 'is_demo' => $i->is_demo, 'lat' => $i->public_lat, 'lng' => $i->public_lng,
                'area_name' => $i->area_name, 'confirm' => $i->confirmations_count, 'created_at' => $i->created_at->toIso8601String(),
                'thumb' => $i->media->first()?->signedUrl()])->values()]), true))))->header('Cache-Control', 'private, max-age=10');
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
            $this->svc->notifyModerators($incident);
        }
        app(\App\Services\Integrations\WebhookDispatcher::class)->dispatch('incident.created', $incident);
        $incident->load(['user', 'media', 'analyses.regions', 'confirmations']);
        return response()->json(['data' => Present::incident($incident, $user, true), 'nearby_notified' => $notified,
            'undo_seconds' => $auto ? config('rescuelens.auto_post_undo_seconds') : null], 201);
    }

    public function update(Request $r, Incident $incident)
    {
        $u = $r->user();
        abort_unless($incident->user_id === $u->id || $u->isModerator(), 403);
        abort_if(in_array($incident->status, ['removed', 'withdrawn']) && ! $u->isModerator(), 409, 'Report can no longer be edited');
        $d = $r->validate(['title' => 'sometimes|string|min:3|max:160', 'description' => 'nullable|string|max:2000',
            'severity' => ['sometimes', Rule::in(Incident::SEVERITIES)], 'category' => ['sometimes', Rule::in(Incident::CATEGORIES)],
            'area_name' => 'nullable|string|max:120']);
        $incident->fill(array_map(fn ($v) => is_string($v) ? strip_tags($v) : $v, $d));
        $incident->edited_at = now();
        $incident->save();
        $who = $incident->user_id === $u->id ? 'reporter' : $u->role;
        $incident->history()->create(['actor_id' => $u->id, 'from_status' => $incident->status, 'to_status' => $incident->status, 'note' => "Edited by {$who}: ".implode(', ', array_keys($d))]);
        AuditLog::record('incident.update', $incident, ['fields' => array_keys($d)]);
        return $this->show($r, $incident);
    }

    /** Soft delete to Trash (owner, moderator or admin). Restorable from /me/trash. */
    public function destroy(Request $r, Incident $incident)
    {
        $u = $r->user();
        abort_unless($incident->user_id === $u->id || $u->isModerator(), 403);
        $incident->deleted_by = $u->id;
        $incident->save();
        $incident->delete();
        AuditLog::record('incident.trash', $incident);
        return response()->json(['data' => ['id' => $incident->public_id, 'deleted' => true]]);
    }

    public function restore(Request $r, string $id)
    {
        $u = $r->user();
        $incident = Incident::onlyTrashed()->where('public_id', $id)->firstOrFail();
        abort_unless($incident->user_id === $u->id || $u->isModerator(), 403);
        $incident->restore();
        $incident->forceFill(['deleted_by' => null])->save();
        AuditLog::record('incident.restore', $incident);
        return $this->show($r, $incident);
    }

    /** Permanently delete one trashed report with its media files. Owner only; moderators may purge any. Cannot be undone. */
    public function forceDelete(Request $r, string $id)
    {
        $u = $r->user();
        $incident = Incident::onlyTrashed()->where('public_id', $id)->firstOrFail();
        abort_unless($incident->user_id === $u->id || $u->isModerator(), 403);
        $this->purge($incident);
        return response()->json(['data' => ['deleted' => 1]]);
    }

    /** Empty Trash: purges the caller's trashed reports; moderators with all=1 purge every trashed report. */
    public function emptyTrash(Request $r)
    {
        $u = $r->user();
        $q = Incident::onlyTrashed();
        if (! ($u->isModerator() && $r->boolean('all'))) $q->where('user_id', $u->id);
        $n = 0;
        $q->chunkById(50, function ($rows) use (&$n) { foreach ($rows as $i) { $this->purge($i); $n++; } });
        return response()->json(['data' => ['deleted' => $n]]);
    }

    private function purge(Incident $incident): void
    {
        foreach (\App\Models\IncidentMedia::where('incident_id', $incident->id)->get() as $m) {
            if ($m->path) \Illuminate\Support\Facades\Storage::disk($m->disk ?: config('filesystems.default'))->delete($m->path);
            $m->delete();
        }
        AuditLog::record('incident.force_delete', $incident);
        $incident->forceDelete();
    }

    /** Trash: own deleted reports; moderators may pass all=1 to see every deleted report. */
    /** Public profile header: no email, phone or location. */
    public function profile(Request $r, \App\Models\User $user)
    {
        $viewer = $r->user('sanctum');
        $mine = $viewer?->id === $user->id;
        $count = Incident::where('user_id', $user->id)->when(! $mine, fn ($q) => $q->where('is_anonymous', false))->count();
        return ['data' => ['id' => $user->public_id, 'name' => $user->name, 'role' => $user->role, 'avatar_url' => Present::avatar($user),
            'joined_at' => $user->created_at?->toIso8601String(), 'reports' => $count, 'is_me' => $mine]];
    }

    public function trash(Request $r)
    {
        $u = $r->user();
        $q = Incident::onlyTrashed()->with(['user', 'media'])->latest('deleted_at');
        if (! ($u->isModerator() && $r->boolean('all'))) {
            $q->where('user_id', $u->id);
        }
        return ['data' => $q->limit(100)->get()->map(fn ($i) => Present::incident($i, $u))];
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

    /** Comments with threaded replies. ?limit=2 returns the latest top level comments for feed previews. */
    public function comments(Request $r, Incident $incident)
    {
        abort_unless(in_array($incident->status, Incident::PUBLIC_STATUSES), 404);
        $viewer = $r->user('sanctum');
        $limit = min(max((int) $r->input('limit', 100), 1), 100);
        $top = $incident->comments()->whereNull('parent_id')->with('user')->latest()->limit($limit)->get()->reverse()->values();
        $replies = IncidentComment::whereIn('parent_id', $top->pluck('id'))->with('user')->oldest()->limit(300)->get();
        $all = $top->concat($replies);
        $liked = $viewer ? \Illuminate\Support\Facades\DB::table('comment_likes')->where('user_id', $viewer->id)->whereIn('comment_id', $all->pluck('id'))->pluck('comment_id')->map(fn ($v) => (int) $v)->all() : [];
        $rows = $all->map(fn ($c) => Present::comment($c, $viewer, $liked))->all();
        return ['data' => \App\Services\I18n\ContentTranslation::apply($rows, $r->input('lang') ?? $viewer?->locale, 'body'),
            'total' => $incident->comments()->count()];
    }

    public function comment(Request $r, Incident $incident)
    {
        abort_unless(in_array($incident->status, Incident::PUBLIC_STATUSES), 404);
        $d = $r->validate(['body' => 'required|string|max:1000', 'parent_id' => 'nullable|integer']);
        $parent = null;
        if (! empty($d['parent_id'])) {
            $parent = IncidentComment::where('incident_id', $incident->id)->findOrFail($d['parent_id']);
            if ($parent->parent_id) { $parent = $parent->parent; } // one level of threading, like most social apps
        }
        $u = $r->user();
        $c = IncidentComment::create(['incident_id' => $incident->id, 'user_id' => $u->id, 'parent_id' => $parent?->id, 'body' => strip_tags($d['body'])]);
        $incident->increment('comments_count');
        if ($parent && $parent->user_id !== $u->id) {
            UserNotification::create(['user_id' => $parent->user_id, 'incident_id' => $incident->id, 'type' => 'comment', 'title' => "{$u->name} replied to your comment on \"{$incident->title}\""]);
        }
        if ($incident->user_id !== $u->id && $incident->user_id !== $parent?->user_id) {
            UserNotification::create(['user_id' => $incident->user_id, 'incident_id' => $incident->id, 'type' => 'comment', 'title' => "{$u->name} commented on \"{$incident->title}\""]);
        }
        return response()->json(['data' => Present::comment($c->load('user'), $u)], 201);
    }

    private function ownComment(Request $r, int $id, bool $trashed = false): IncidentComment
    {
        $c = ($trashed ? IncidentComment::onlyTrashed() : IncidentComment::query())->findOrFail($id);
        abort_unless($c->user_id === $r->user()->id || $r->user()->isModerator(), 403);
        return $c;
    }

    public function updateComment(Request $r, int $id)
    {
        $c = $this->ownComment($r, $id);
        $d = $r->validate(['body' => 'required|string|max:1000']);
        $c->update(['body' => strip_tags($d['body']), 'edited_at' => now()]);
        AuditLog::record('comment.update', $c);
        return ['data' => Present::comment($c->load('user'), $r->user())];
    }

    public function deleteComment(Request $r, int $id)
    {
        $c = $this->ownComment($r, $id);
        $n = 1 + IncidentComment::where('parent_id', $c->id)->count();
        IncidentComment::where('parent_id', $c->id)->delete();
        $c->delete();
        Incident::whereKey($c->incident_id)->where('comments_count', '>=', $n)->decrement('comments_count', $n);
        AuditLog::record('comment.trash', $c);
        return response()->json(['data' => ['id' => $c->id, 'deleted' => true]]);
    }

    public function restoreComment(Request $r, int $id)
    {
        $c = $this->ownComment($r, $id, true);
        $c->restore();
        $n = 1 + IncidentComment::onlyTrashed()->where('parent_id', $c->id)->where('deleted_at', '>=', $c->updated_at->subSeconds(5))->restore();
        Incident::whereKey($c->incident_id)->increment('comments_count', $n);
        AuditLog::record('comment.restore', $c);
        return ['data' => Present::comment($c->load('user'), $r->user())];
    }

    public function likeComment(Request $r, int $id)
    {
        $c = IncidentComment::findOrFail($id);
        $t = \Illuminate\Support\Facades\DB::table('comment_likes');
        $exists = (clone $t)->where(['comment_id' => $c->id, 'user_id' => $r->user()->id])->exists();
        if ($exists) {
            (clone $t)->where(['comment_id' => $c->id, 'user_id' => $r->user()->id])->delete();
            $c->decrement('likes_count');
        } else {
            $t->insert(['comment_id' => $c->id, 'user_id' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $c->increment('likes_count');
        }
        return ['data' => ['liked' => ! $exists, 'likes' => (int) $c->fresh()->likes_count]];
    }
}
