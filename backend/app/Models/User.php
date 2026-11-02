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
    protected $fillable = ['name', 'email', 'password', 'locale', 'alert_lat', 'alert_lng', 'alert_radius_km', 'ai_consent', 'country_code', 'distance_unit', 'timezone', 'theme', 'notify_prefs'];

    /** Defaults mirror the column defaults so a freshly created model reports them. */
    protected $attributes = ['role' => 'citizen', 'locale' => 'en'];

    protected $hidden = ['password', 'remember_token', 'alert_lat', 'alert_lng'];

    public const NOTIFY_DEFAULTS = ['push' => true, 'categories' => [], 'quiet_start' => null, 'quiet_end' => null, 'min_severity' => 'low'];

    /** Effective notification preferences with defaults filled in. Empty categories means all. */
    public function notifyPrefs(): array
    {
        return array_merge(self::NOTIFY_DEFAULTS, $this->notify_prefs ?? []);
    }

    /** Whether a nearby alert of this category and severity should reach the user right now. Critical alerts ignore quiet hours. */
    public function wantsAlert(string $category, string $severity, ?\DateTimeInterface $at = null): bool
    {
        $p = $this->notifyPrefs();
        $rank = ['low' => 0, 'moderate' => 1, 'high' => 2, 'critical' => 3];
        if ($p['categories'] && ! in_array($category, $p['categories'], true)) return false;
        if (($rank[$severity] ?? 1) < ($rank[$p['min_severity']] ?? 0)) return false;
        if ($severity !== 'critical' && $p['quiet_start'] && $p['quiet_end']) {
            $t = \Illuminate\Support\Carbon::instance($at ?? now())->setTimezone($this->timezone ?: config('app.timezone'))->format('H:i');
            $in = $p['quiet_start'] <= $p['quiet_end'] ? ($t >= $p['quiet_start'] && $t < $p['quiet_end']) : ($t >= $p['quiet_start'] || $t < $p['quiet_end']);
            if ($in) return false;
        }
        return true;
    }

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
            'notify_prefs' => 'array',
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
