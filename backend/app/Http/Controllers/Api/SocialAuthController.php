<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * OAuth login with Facebook and Google via Laravel Socialite (stateless).
 * Callback hands the SPA a one time code (60s) which it exchanges for a Sanctum token,
 * so tokens never appear in browser history or server logs.
 */
class SocialAuthController extends Controller
{
    private const PROVIDERS = ['facebook', 'google'];

    private function configured(string $p): bool
    {
        return filled(config("services.$p.client_id")) && filled(config("services.$p.client_secret")) && filled(config("services.$p.redirect"));
    }

    public function redirect(string $provider)
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);
        if (! $this->configured($provider)) {
            return response()->json(['message' => ucfirst($provider).' login is not configured on this server yet.', 'code' => 'social_not_configured'], 503);
        }
        $driver = Socialite::driver($provider)->stateless();
        if ($provider === 'facebook') $driver->scopes(['email']);
        return $driver->redirect();
    }

    public function callback(string $provider)
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);
        $front = rtrim(config('rescuelens.frontend_url'), '/').'/auth/callback';
        if (! $this->configured($provider)) {
            return redirect($front.'?error=social_not_configured');
        }
        try {
            $s = Socialite::driver($provider)->stateless()->user();
        } catch (\Throwable) {
            return redirect($front.'?error=social_failed');
        }
        if (! $s->getEmail()) {
            return redirect($front.'?error=social_no_email');
        }
        $user = User::where('email', strtolower($s->getEmail()))->first();
        if (! $user) {
            $user = User::create(['name' => $s->getName() ?: 'RescueLens user', 'email' => strtolower($s->getEmail()), 'password' => Str::random(40)]);
            $user->forceFill(['email_verified_at' => now()])->save();
        }
        AuditLog::record('auth.social_login', $user, ['provider' => $provider]);
        $code = Str::random(48);
        Cache::put('social_code:'.$code, $user->id, 60);
        return redirect($front.'?code='.$code);
    }

    public function exchange(\Illuminate\Http\Request $r)
    {
        $r->validate(['code' => 'required|string|size:48']);
        $id = Cache::pull('social_code:'.$r->code);
        abort_unless($id, 422, 'Invalid or expired code');
        $u = User::findOrFail($id);
        return ['user' => \App\Support\Present::user($u), 'token' => $u->createToken('social')->plainTextToken];
    }
}
