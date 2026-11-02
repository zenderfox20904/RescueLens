<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** Password reset with a 6 digit emailed code. Responses never reveal whether an email exists. */
class PasswordResetController extends Controller
{
    public function forgot(Request $r)
    {
        $d = $r->validate(['email' => 'required|email:rfc|max:160']);
        $u = User::where('email', strtolower($d['email']))->first();
        if ($u) {
            $code = (string) random_int(100000, 999999);
            DB::table('auth_codes')->where('user_id', $u->id)->where('purpose', 'password_reset')->whereNull('used_at')->delete();
            DB::table('auth_codes')->insert(['user_id' => $u->id, 'purpose' => 'password_reset', 'code_hash' => hash('sha256', $code),
                'expires_at' => now()->addMinutes(15), 'created_at' => now(), 'updated_at' => now()]);
            try {
                Mail::raw("Your RescueLens password reset code is $code\n\nIt expires in 15 minutes. If you did not ask for this, you can ignore this email.\n\n© 2026 RescueLens",
                    fn ($m) => $m->to($u->email)->subject('RescueLens password reset code'));
            } catch (\Throwable $e) {
                report(new \RuntimeException('Password reset mail failed: '.class_basename($e)));
            }
            AuditLog::record('auth.password_reset_requested', $u);
        }
        return ['message' => 'If an account exists for that email, a reset code has been sent.'];
    }

    public function reset(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'code' => 'required|digits:6', 'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()]]);
        $fail = fn () => throw ValidationException::withMessages(['code' => 'The code is invalid or has expired.']);
        $u = User::where('email', strtolower($d['email']))->first() ?? $fail();
        $row = DB::table('auth_codes')->where('user_id', $u->id)->where('purpose', 'password_reset')->whereNull('used_at')->where('expires_at', '>', now())->latest('id')->first() ?? $fail();
        if ($row->attempts >= 5) $fail();
        if (! hash_equals($row->code_hash, hash('sha256', $d['code']))) {
            DB::table('auth_codes')->where('id', $row->id)->increment('attempts');
            $fail();
        }
        DB::table('auth_codes')->where('id', $row->id)->update(['used_at' => now()]);
        $u->forceFill(['password' => Hash::make($d['password'])])->save();
        $u->tokens()->delete();
        AuditLog::record('auth.password_reset', $u);
        return ['message' => 'Password updated. You can sign in now.'];
    }
}
