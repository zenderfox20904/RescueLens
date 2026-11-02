<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireRole
{
    public function handle(Request $request, Closure $next, string $role)
    {
        $u = $request->user();
        $ok = $u && match ($role) { 'moderator' => $u->isModerator(), 'admin' => $u->isAdmin(), default => false };
        abort_unless($ok, 403, 'You do not have permission for this action.');
        return $next($request);
    }
}
