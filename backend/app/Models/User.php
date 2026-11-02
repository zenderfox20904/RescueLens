<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use SoftDeletes;
    use HasApiTokens, HasFactory, Notifiable, \Illuminate\Database\Eloquent\SoftDeletes;

    public const ROLES = ['citizen', 'responder', 'moderator', 'admin'];

    /** Only these fields are mass assignable. Role is never user writable. */
    protected $fillable = ['name', 'email', 'password', 'locale', 'alert_lat', 'alert_lng', 'alert_radius_km', 'ai_consent', 'country_code', 'distance_unit', 'timezone'];

    /** Defaults mirror the column defaults so a freshly created model reports them. */
    protected $attributes = ['role' => 'citizen', 'locale' => 'en'];

    protected $hidden = ['password', 'remember_token', 'alert_lat', 'alert_lng'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'auto_post_enabled' => 'boolean',
            'auto_post_consent_at' => 'datetime',
            'password' => 'hashed',
            'ai_consent' => 'boolean',
            'is_demo' => 'boolean',
            'alert_lat' => 'float',
            'alert_lng' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $u) {
            $u->public_id ??= (string) \Illuminate\Support\Str::ulid();
        });
    }

    public function isModerator(): bool
    {
        return in_array($this->role, ['moderator', 'admin'], true);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function incidents()
    {
        return $this->hasMany(Incident::class);
    }
}
