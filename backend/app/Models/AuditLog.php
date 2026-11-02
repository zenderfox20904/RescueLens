<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';
    protected $guarded = ['id'];
    public const UPDATED_AT = null;
    protected $casts = ['meta' => 'array'];

    public static function record(string $action, ?Model $subject = null, array $meta = []): void
    {
        $req = request();
        static::create([
            'actor_id' => $req?->user()?->id,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'ip_hash' => $req?->ip() ? hash_hmac('sha256', $req->ip(), (string) config('app.key')) : null,
            'meta' => $meta ?: null,
        ]);
    }
}
