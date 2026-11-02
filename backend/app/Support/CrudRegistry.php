<?php

namespace App\Support;

use App\Models\CameraFeed;
use App\Models\EmergencyResource;
use App\Models\Incident;
use App\Models\IncidentComment;
use App\Models\Language;
use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * One definition per admin managed entity: model, route key, searchable, filterable and sortable columns,
 * validation and a safe presenter. Exact incident coordinates and secrets are never included.
 */
final class CrudRegistry
{
    public static function all(): array
    {
        $cats = array_keys(config('rescuelens.categories', [])) ?: null;
        return [
            'users' => [
                'model' => User::class, 'key' => 'public_id', 'search' => ['name', 'email'],
                'filters' => ['role' => User::ROLES, 'locale' => null], 'sort' => ['name', 'email', 'role', 'created_at'],
                'create' => ['name' => 'required|string|max:120', 'email' => 'required|email|max:190|unique:users,email', 'password' => 'required|string|min:8|max:200', 'locale' => 'nullable|string|max:12', 'country_code' => 'nullable|alpha|size:2'],
                'update' => fn ($m) => ['name' => 'sometimes|string|max:120', 'email' => ['sometimes', 'email', 'max:190', Rule::unique('users', 'email')->ignore($m->id)], 'locale' => 'sometimes|string|max:12', 'country_code' => 'sometimes|alpha|size:2'],
                'present' => fn (User $u) => ['id' => $u->public_id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role, 'locale' => $u->locale, 'country_code' => $u->country_code,
                    'incidents' => $u->incidents()->count(), 'is_demo' => (bool) $u->is_demo, 'created_at' => $u->created_at?->toIso8601String(), 'deleted_at' => $u->deleted_at?->toIso8601String()],
            ],
            'incidents' => [
                'model' => Incident::class, 'key' => 'public_id', 'search' => ['title', 'description', 'area_name'],
                'filters' => ['status' => ['reported', 'corroborated', 'verified', 'disputed', 'resolved', 'withdrawn', 'merged'], 'severity' => ['low', 'moderate', 'high', 'critical'], 'category' => $cats, 'source' => ['user', 'auto_post', 'camera']],
                'sort' => ['title', 'severity', 'status', 'created_at', 'confirmations_count'],
                'create' => ['title' => 'required|string|max:160', 'description' => 'nullable|string|max:5000', 'category' => 'required|string|max:30', 'severity' => 'required|in:low,moderate,high,critical', 'lat' => 'required|numeric|between:-90,90', 'lng' => 'required|numeric|between:-180,180', 'area_name' => 'nullable|string|max:120', 'address' => 'nullable|string|max:300'],
                'update' => fn () => ['title' => 'sometimes|string|max:160', 'description' => 'sometimes|nullable|string|max:5000', 'category' => 'sometimes|string|max:30', 'severity' => 'sometimes|in:low,moderate,high,critical', 'area_name' => 'sometimes|nullable|string|max:120', 'address' => 'sometimes|nullable|string|max:300', 'lat' => 'sometimes|numeric|between:-90,90', 'lng' => 'sometimes|numeric|between:-180,180'],
                'present' => fn (Incident $i) => ['id' => $i->public_id, 'title' => $i->title, 'description' => $i->description, 'category' => $i->category, 'severity' => $i->severity, 'status' => $i->status,
                    'source' => $i->source, 'ai_unverified' => (bool) $i->ai_unverified, 'area_name' => $i->area_name, 'address' => $i->address, 'public_lat' => (float) $i->public_lat, 'public_lng' => (float) $i->public_lng,
                    'reporter' => $i->is_anonymous ? null : $i->user?->name, 'confirmations' => $i->confirmations_count, 'comments' => $i->comments_count, 'is_demo' => (bool) $i->is_demo,
                    'created_at' => $i->created_at?->toIso8601String(), 'deleted_at' => $i->deleted_at?->toIso8601String()],
            ],
            'comments' => [
                'model' => IncidentComment::class, 'key' => 'id', 'search' => ['body'], 'filters' => [], 'sort' => ['created_at'],
                'create' => ['incident_id' => 'required|string|exists:incidents,public_id', 'body' => 'required|string|max:2000'],
                'update' => fn () => ['body' => 'sometimes|string|max:2000'],
                'present' => fn (IncidentComment $c) => ['id' => (string) $c->id, 'body' => $c->body, 'author' => $c->user?->name, 'incident_id' => $c->incident?->public_id, 'incident_title' => $c->incident?->title,
                    'created_at' => $c->created_at?->toIso8601String(), 'deleted_at' => $c->deleted_at?->toIso8601String()],
            ],
            'emergency-resources' => [
                'model' => EmergencyResource::class, 'key' => 'id', 'search' => ['name', 'name_bn', 'phone', 'jurisdiction', 'source_name'],
                'filters' => ['country_code' => null, 'category' => null], 'sort' => ['name', 'country_code', 'category', 'created_at'],
                'create' => ['country_code' => ['required', 'regex:/^[A-Za-z]{2}$/'], 'name' => 'required|string|max:160', 'name_bn' => 'nullable|string|max:160', 'phone' => ['required', 'regex:/^[0-9+ ]{2,20}$/'], 'category' => 'required|string|max:30', 'jurisdiction' => 'nullable|string|max:120', 'source_name' => 'required|string|max:160', 'source_url' => 'required|url|starts_with:https://'],
                'update' => fn () => ['name' => 'sometimes|string|max:160', 'name_bn' => 'sometimes|nullable|string|max:160', 'phone' => ['sometimes', 'regex:/^[0-9+ ]{2,20}$/'], 'category' => 'sometimes|string|max:30', 'jurisdiction' => 'sometimes|string|max:120', 'source_name' => 'sometimes|string|max:160', 'source_url' => 'sometimes|url|starts_with:https://', 'is_active' => 'sometimes|boolean'],
                'present' => fn (EmergencyResource $e) => ['id' => (string) $e->id, 'name' => $e->name, 'name_bn' => $e->name_bn, 'phone' => $e->phone, 'category' => $e->category, 'country_code' => $e->country_code, 'jurisdiction' => $e->jurisdiction,
                    'source_name' => $e->source_name, 'source_url' => $e->source_url, 'verified' => $e->verified_at !== null, 'is_active' => $e->is_active, 'created_at' => $e->created_at?->toIso8601String(), 'deleted_at' => $e->deleted_at?->toIso8601String()],
            ],
            'camera-feeds' => [
                'model' => CameraFeed::class, 'key' => 'public_id', 'search' => ['name', 'area_name'], 'filters' => ['kind' => ['snapshot_url', 'upload']], 'sort' => ['name', 'created_at', 'last_polled_at'],
                'create' => null, 'update' => null, // created and edited through CameraFeedController (allowlist and SSRF rules)
                'present' => fn (CameraFeed $f) => ['id' => $f->public_id, 'name' => $f->name, 'kind' => $f->kind, 'snapshot_host' => $f->snapshot_url ? parse_url($f->snapshot_url, PHP_URL_HOST) : null,
                    'area_name' => $f->area_name, 'is_active' => $f->is_active, 'interval_seconds' => $f->interval_seconds, 'last_polled_at' => $f->last_polled_at?->toIso8601String(), 'last_error' => $f->last_error,
                    'frames' => $f->frames()->count(), 'created_at' => $f->created_at?->toIso8601String(), 'deleted_at' => $f->deleted_at?->toIso8601String()],
            ],
            'languages' => [
                'model' => Language::class, 'key' => 'code', 'search' => ['code', 'native_name', 'english_name'], 'filters' => [], 'sort' => ['sort', 'code', 'english_name'],
                'create' => ['code' => ['required', 'regex:/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', 'unique:languages,code'], 'native_name' => 'required|string|max:80', 'english_name' => 'required|string|max:80', 'rtl' => 'boolean', 'enabled' => 'boolean'],
                'update' => fn () => ['native_name' => 'sometimes|string|max:80', 'english_name' => 'sometimes|string|max:80', 'rtl' => 'sometimes|boolean', 'enabled' => 'sometimes|boolean'],
                'present' => fn (Language $l) => Language::present($l) + ['id' => $l->code, 'created_at' => $l->created_at?->toIso8601String(), 'deleted_at' => $l->deleted_at?->toIso8601String()],
            ],
        ];
    }

    public static function get(string $entity): array
    {
        $d = self::all()[$entity] ?? null;
        abort_unless($d, 404, 'Unknown entity.');
        return $d;
    }
}
