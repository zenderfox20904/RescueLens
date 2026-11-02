<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Services\IncidentService;
use App\Support\CrudRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Generic admin CRUD with soft deletes: list (search, filter, sort, paginate, trash), show, create, update,
 * delete (to Trash), restore and permanent delete. Authorization: CrudPolicy.
 */
class CrudController extends Controller
{
    private function def(Request $r, string $entity, string $action): array
    {
        $d = CrudRegistry::get($entity);
        Gate::authorize('crud', [$entity, $action]);
        return $d;
    }

    private function find(array $d, string $id, bool $withTrashed = false)
    {
        $q = $d['model']::query();
        if ($withTrashed) $q->withTrashed();
        return $q->where($d['key'], $id)->firstOrFail();
    }

    public function meta()
    {
        return ['data' => collect(CrudRegistry::all())->map(fn ($d, $k) => ['entity' => $k, 'filters' => $d['filters'], 'sort' => $d['sort'],
            'creatable' => $d['create'] !== null, 'editable' => $d['update'] !== null, 'allowed' => Gate::allows('crud', [$k, 'view'])])->values()];
    }

    public function index(Request $r, string $entity)
    {
        $d = $this->def($r, $entity, 'view');
        $q = $d['model']::query();
        if ($r->input('trashed') === 'only') $q->onlyTrashed();
        if ($s = trim((string) $r->input('q'))) {
            $q->where(fn ($w) => collect($d['search'])->each(fn ($c) => $w->orWhere($c, 'like', '%'.addcslashes($s, '%_').'%')));
        }
        foreach ($d['filters'] as $col => $allowed) {
            $v = $r->input("filter.$col");
            if ($v !== null && $v !== '' && ($allowed === null || in_array($v, $allowed, true))) $q->where($col, $v);
        }
        $sort = in_array($r->input('sort'), $d['sort'], true) ? $r->input('sort') : $d['sort'][array_key_last($d['sort'])];
        $q->orderBy($sort, $r->input('dir') === 'asc' ? 'asc' : 'desc')->orderBy($d['model'] === \App\Models\Language::class ? 'code' : 'id');
        $p = $q->paginate(min(100, max(5, (int) $r->input('per_page', 20))));
        $rows = collect($p->items())->map($d['present'])->all();
        if ($entity === 'incidents') $rows = \App\Services\I18n\ContentTranslation::apply($rows, $r->input('lang'));
        return ['data' => $rows, 'meta' => ['total' => $p->total(), 'page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'per_page' => $p->perPage(),
            'trash_count' => $d['model']::onlyTrashed()->count()]];
    }

    public function show(Request $r, string $entity, string $id)
    {
        $d = $this->def($r, $entity, 'view');
        return ['data' => ($d['present'])($this->find($d, $id, true))];
    }

    public function store(Request $r, string $entity)
    {
        $d = $this->def($r, $entity, 'create');
        abort_if($d['create'] === null, 405, 'Create this item from its dedicated screen.');
        $data = $r->validate($d['create']);
        if ($entity === 'incidents') {
            $data += ['user_id' => $r->user()->id, 'status' => 'reported', 'public_lat' => Incident::roundPublic($data['lat']), 'public_lng' => Incident::roundPublic($data['lng'])];
        } elseif ($entity === 'comments') {
            $inc = Incident::where('public_id', $data['incident_id'])->firstOrFail();
            $data = ['incident_id' => $inc->id, 'user_id' => $r->user()->id, 'body' => strip_tags($data['body'])];
        } elseif ($entity === 'emergency-resources') {
            $data['country_code'] = strtoupper($data['country_code']);
            $data['jurisdiction'] ??= $data['country_code'];
            $data += ['is_active' => true, 'verified_at' => null]; // starts unverified; verified separately
        } elseif ($entity === 'languages') {
            $data['rtl'] ??= in_array(explode('-', $data['code'])[0], \App\Models\Language::RTL, true);
        }
        if ($entity === 'organizations') { $v = $data['verified'] ?? false; unset($data['verified']); $data['verified_at'] = $v ? now() : null; }
        $m = $d['model']::query()->forceCreate($data);
        if ($entity === 'comments') $m->incident?->increment('comments_count');
        AuditLog::record("crud.$entity.create", $m);
        if ($entity === 'incidents') Incident::bumpCache();
        return response()->json(['data' => ($d['present'])($m->fresh())], 201);
    }

    public function update(Request $r, string $entity, string $id)
    {
        $d = $this->def($r, $entity, 'update');
        abort_if($d['update'] === null, 405, 'Edit this item from its dedicated screen.');
        $m = $this->find($d, $id);
        $data = $r->validate(($d['update'])($m));
        if (isset($data['body'])) $data['body'] = strip_tags($data['body']);
        if ($entity === 'organizations' && array_key_exists('verified', $data)) { $data['verified_at'] = $data['verified'] ? ($m->verified_at ?? now()) : null; unset($data['verified']); }
        if ($entity === 'incidents' && isset($data['lat'], $data['lng'])) $data += ['public_lat' => Incident::roundPublic($data['lat']), 'public_lng' => Incident::roundPublic($data['lng'])];
        $m->forceFill($data)->save(); // fields are limited by the validated allowlist; role is never accepted here
        AuditLog::record("crud.$entity.update", $m, ['fields' => array_keys($data)]);
        if ($entity === 'incidents') Incident::bumpCache();
        return ['data' => ($d['present'])($m->fresh())];
    }

    public function destroy(Request $r, string $entity, string $id)
    {
        $d = $this->def($r, $entity, 'delete');
        $m = $this->find($d, $id);
        abort_if($entity === 'users' && $m->id === $r->user()->id, 422, 'You cannot delete your own account here.');
        abort_if($entity === 'languages' && $m->code === 'en', 422, 'English is the default language and cannot be deleted.');
        $m->delete();
        if ($entity === 'users') $m->tokens()->delete();
        AuditLog::record("crud.$entity.delete", $m);
        if ($entity === 'incidents') Incident::bumpCache();
        return response()->json(['data' => ($d['present'])($m)]);
    }

    public function restore(Request $r, string $entity, string $id)
    {
        $d = $this->def($r, $entity, 'restore');
        $m = $this->find($d, $id, true);
        $m->restore();
        AuditLog::record("crud.$entity.restore", $m);
        if ($entity === 'incidents') Incident::bumpCache();
        return ['data' => ($d['present'])($m->fresh())];
    }

    public function forceDelete(Request $r, string $entity, string $id)
    {
        $d = $this->def($r, $entity, 'force');
        $m = $this->find($d, $id, true);
        abort_unless($m->trashed(), 422, 'Move the item to Trash before deleting it permanently.');
        AuditLog::record("crud.$entity.force_delete", null, ['entity' => $entity, 'id' => $id]);
        $m->forceDelete();
        return response()->noContent();
    }
}
