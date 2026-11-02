<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\EmergencyResource;
use App\Models\Incident;
use App\Models\User;
use App\Services\IncidentService;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ModerationController extends Controller
{
    public function __construct(private IncidentService $svc) {}

    public function queue(Request $r)
    {
        $d = $r->validate(['status' => ['nullable', Rule::in(Incident::STATUSES)]]);
        $q = Incident::with(['user', 'media', 'analyses.regions', 'confirmations'])->whereNull('merged_into_id');
        $q = isset($d['status']) ? $q->where('status', $d['status']) : $q->whereIn('status', ['reported', 'corroborated', 'disputed']);
        return ['data' => $q->orderByRaw("FIELD(severity,'critical','high','moderate','low')")->latest()->limit(100)->get()
            ->map(fn ($i) => Present::incident($i, $r->user(), true))];
    }

    public function setStatus(Request $r, Incident $incident)
    {
        $d = $r->validate(['status' => ['required', Rule::in(['reported', 'verified', 'disputed', 'resolved', 'expired', 'removed'])], 'note' => 'nullable|string|max:500']);
        $this->svc->setStatus($incident, $d['status'], $r->user(), $d['note'] ?? null);
        AuditLog::record('moderation.status', $incident, ['to' => $d['status']]);
        return ['data' => Present::incident($incident->fresh(['user', 'media', 'analyses.regions', 'confirmations']), $r->user(), true)];
    }

    public function merge(Request $r, Incident $incident)
    {
        $d = $r->validate(['into' => 'required|string|size:26']);
        $target = Incident::where('public_id', $d['into'])->firstOrFail();
        abort_if($target->id === $incident->id, 422, 'Cannot merge into itself');
        $incident->update(['merged_into_id' => $target->id]);
        $incident->media()->update(['incident_id' => $target->id]);
        $incident->history()->create(['actor_id' => $r->user()->id, 'from_status' => $incident->status, 'to_status' => $incident->status, 'note' => 'Merged as duplicate into '.$target->public_id]);
        AuditLog::record('moderation.merge', $incident, ['into' => $target->public_id]);
        return ['ok' => true];
    }

    public function users(Request $r)
    {
        return ['data' => User::latest()->limit(200)->get()->map(fn ($u) => Present::user($u) + ['reports' => $u->incidents()->count()])];
    }

    public function setRole(Request $r, string $user)
    {
        abort_unless($r->user()->isAdmin(), 403);
        $d = $r->validate(['role' => ['required', Rule::in(User::ROLES)]]);
        $u = User::where('public_id', $user)->firstOrFail();
        abort_if($u->id === $r->user()->id, 422, 'You cannot change your own role');
        $u->role = $d['role'];
        $u->save();
        AuditLog::record('admin.role', $u, ['role' => $d['role']]);
        return ['data' => Present::user($u)];
    }

    public function audit(Request $r)
    {
        abort_unless($r->user()->isAdmin(), 403);
        return ['data' => AuditLog::latest('id')->limit(200)->get(['id', 'actor_id', 'action', 'subject_type', 'subject_id', 'meta', 'created_at'])];
    }

    public function verifyResource(Request $r, EmergencyResource $resource)
    {
        abort_unless($r->user()->isAdmin(), 403);
        $resource->update(['verified_at' => now(), 'last_reviewed_at' => now(), 'verified_by' => $r->user()->id]);
        AuditLog::record('admin.resource_verify', $resource);
        return ['ok' => true];
    }

    /** Optional Facebook Page post. Requires config flag and explicit confirm=true from a moderator. */
    public function postToFacebookPage(Request $r, Incident $incident, \App\Services\Integrations\FacebookPagePublisher $fb)
    {
        $r->validate(['confirm' => 'accepted']);
        if (! $fb->enabled()) {
            return response()->json(['message' => 'Facebook Page posting is disabled on this server.', 'code' => 'fb_page_disabled'], 503);
        }
        abort_unless($incident->status === 'verified', 409, 'Only moderator verified incidents can be shared to the Page.');
        $out = $fb->publish($incident);
        \App\Models\AuditLog::record('incident.fb_page_post', $incident, ['post_id' => $out['post_id'] ?? null]);
        return ['data' => $out];
    }
}
