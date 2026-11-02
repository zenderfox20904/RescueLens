<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $r)
    {
        $d = $r->validate([
            'name' => 'required|string|max:80',
            'email' => 'required|email:rfc|max:160|unique:users,email',
            'password' => ['required', Password::min(8)->letters()->numbers()],
            'locale' => ['nullable', 'regex:/^[a-z]{2,3}$/'],
            'device_name' => 'nullable|string|max:60',
            'account_type' => ['nullable', \Illuminate\Validation\Rule::in(array_merge(['citizen'], \App\Models\Organization::TYPES))],
        ]);
        $type = $d['account_type'] ?? 'citizen';
        $org = $type === 'citizen' ? null : $r->validate(collect(\App\Http\Controllers\Api\OrganizationController::rules())->except('type')->mapWithKeys(fn ($v, $k) => ["organization.$k" => $v])->all())['organization'];
        $u = \Illuminate\Support\Facades\DB::transaction(function () use ($d, $type, $org) {
            $u = User::create(['name' => $d['name'], 'email' => strtolower($d['email']), 'password' => $d['password'], 'locale' => $d['locale'] ?? 'en']);
            $u->forceFill(['account_type' => $type])->save();
            // Organizations start unverified; an admin verifies them before they receive dispatches or appear in Nearby help.
            if ($org) \App\Models\Organization::create($org + ['type' => $type, 'user_id' => $u->id]);
            return $u;
        });
        AuditLog::record('auth.register', $u, ['account_type' => $type]);
        return response()->json(['user' => Present::user($u), 'token' => $u->createToken($d['device_name'] ?? 'web')->plainTextToken], 201);
    }

    public function login(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'password' => 'required|string', 'device_name' => 'nullable|string|max:60']);
        $u = User::where('email', strtolower($d['email']))->first();
        if (! $u || ! Hash::check($d['password'], $u->password)) {
            AuditLog::record('auth.login_failed', null, ['email_hash' => hash('sha256', strtolower($d['email']))]);
            throw ValidationException::withMessages(['email' => 'Invalid email or password.']);
        }
        AuditLog::record('auth.login', $u);
        return ['user' => Present::user($u), 'token' => $u->createToken($d['device_name'] ?? 'web')->plainTextToken];
    }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()?->delete();
        return response()->noContent();
    }

    public function me(Request $r)
    {
        $u = $r->user();
        $stats = [
            'reports' => \App\Models\Incident::where('user_id', $u->id)->count(),
            'verified' => \App\Models\Incident::where('user_id', $u->id)->where('status', 'verified')->count(),
            'confirmations' => \App\Models\IncidentConfirmation::where('user_id', $u->id)->count(),
        ];
        return ['user' => Present::user($u), 'stats' => $stats];
    }

    /** Avatar upload: re-encoded server side so EXIF (including GPS) is stripped. */
    public function avatar(Request $r, \App\Services\ImageProcessor $img)
    {
        $r->validate(['image' => 'required|file|max:'.config('rescuelens.upload_max_kb').'|mimes:jpg,jpeg,png,webp']);
        $meta = $img->store($r->file('image'));
        $u = $r->user();
        if ($u->avatar_path) \Illuminate\Support\Facades\Storage::disk('local')->delete($u->avatar_path);
        $u->forceFill(['avatar_path' => $meta['path']])->save();
        \App\Models\AuditLog::record('profile.avatar', $u);
        return ['user' => Present::user($u->fresh())];
    }

    public function avatarFile(string $user)
    {
        $u = \App\Models\User::where('public_id', $user)->whereNotNull('avatar_path')->firstOrFail();
        return response(\Illuminate\Support\Facades\Storage::disk('local')->get($u->avatar_path), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=600', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function changePassword(Request $r)
    {
        $d = $r->validate(['current_password' => 'required|string', 'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()]]);
        $u = $r->user();
        if ($u->password && ! \Illuminate\Support\Facades\Hash::check($d['current_password'], $u->password)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['current_password' => 'Current password is incorrect.']);
        }
        $u->forceFill(['password' => \Illuminate\Support\Facades\Hash::make($d['password'])])->save();
        $u->tokens()->where('id', '!=', $u->currentAccessToken()?->id)->delete();
        \App\Models\AuditLog::record('profile.password_changed', $u);
        return response()->noContent();
    }

    public function updateMe(Request $r)
    {
        $d = $r->validate([
            'name' => 'sometimes|string|min:2|max:80', 'phone' => ['sometimes', 'nullable', 'regex:/^\+?[0-9 ()]{6,20}$/'], 'bio' => 'sometimes|nullable|string|max:200', 'locale' => ['sometimes', 'regex:/^[a-z]{2,3}$/'], 'country_code' => 'sometimes|string|size:2|alpha', 'distance_unit' => 'sometimes|in:km,mi', 'timezone' => 'sometimes|nullable|timezone', 'ai_consent' => 'sometimes|boolean', 'auto_post_enabled' => 'sometimes|boolean', 'auto_post_consent' => 'accepted_if:auto_post_enabled,true,1',
            'alert_lat' => 'nullable|numeric|between:-90,90', 'alert_lng' => 'nullable|numeric|between:-180,180',
            'alert_radius_km' => 'sometimes|integer|between:1,50', 'theme' => 'sometimes|in:light,dark,system',
            'notify_prefs' => 'sometimes|array', 'notify_prefs.push' => 'sometimes|boolean', 'notify_prefs.categories' => 'sometimes|array',
            'notify_prefs.categories.*' => ['string', \Illuminate\Validation\Rule::in(\App\Models\Incident::CATEGORIES)],
            'notify_prefs.quiet_start' => 'sometimes|nullable|date_format:H:i', 'notify_prefs.quiet_end' => 'sometimes|nullable|date_format:H:i',
            'notify_prefs.min_severity' => 'sometimes|in:low,moderate,high,critical',
        ]);
        if (isset($d['notify_prefs'])) $d['notify_prefs'] = array_intersect_key(array_merge($r->user()->notifyPrefs(), $d['notify_prefs']), \App\Models\User::NOTIFY_DEFAULTS);
        if (isset($d["country_code"])) $d["country_code"] = strtoupper($d["country_code"]);
        // Auto post is opt in: enabling it requires explicit consent, which is timestamped and audited.
        unset($d['auto_post_consent']);
        if (array_key_exists('auto_post_enabled', $d)) {
            $d['auto_post_consent_at'] = $d['auto_post_enabled'] ? now() : null;
            \App\Models\AuditLog::record($d['auto_post_enabled'] ? 'consent.auto_post.granted' : 'consent.auto_post.revoked', $r->user());
        }
        $r->user()->forceFill($d)->save();
        return ['user' => Present::user($r->user()->fresh())];
    }

    public function destroyMe(Request $r)
    {
        $r->validate(['password' => 'required|string']);
        $u = $r->user();
        if (! Hash::check($r->password, $u->password)) {
            throw ValidationException::withMessages(['password' => 'Password is incorrect.']);
        }
        AuditLog::record('auth.account_deleted', $u);
        $u->tokens()->delete();
        $u->delete();
        return response()->noContent();
    }
}
