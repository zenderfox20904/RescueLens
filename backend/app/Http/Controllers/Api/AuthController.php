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
            'locale' => 'nullable|in:bn,en',
            'device_name' => 'nullable|string|max:60',
        ]);
        $u = User::create(['name' => $d['name'], 'email' => strtolower($d['email']), 'password' => $d['password'], 'locale' => $d['locale'] ?? 'bn']);
        AuditLog::record('auth.register', $u);
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
        return ['user' => Present::user($r->user())];
    }

    public function updateMe(Request $r)
    {
        $d = $r->validate([
            'name' => 'sometimes|string|max:80', 'locale' => 'sometimes|in:bn,en', 'ai_consent' => 'sometimes|boolean',
            'alert_lat' => 'nullable|numeric|between:-90,90', 'alert_lng' => 'nullable|numeric|between:-180,180',
            'alert_radius_km' => 'sometimes|integer|between:1,50',
        ]);
        $r->user()->update($d);
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
