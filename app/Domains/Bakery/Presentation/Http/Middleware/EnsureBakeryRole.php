<?php

namespace Domains\Bakery\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBakeryRole
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);
        if ($role === 'legacy-api') {
            abort_unless($user->bakery_id === null, 403);
        } else {
            abort_unless($user->status->value === 'verify', 403);
            abort_unless($role === 'admin' ? $user->role->value === 'admin' : ($user->role->value === 'user' && $user->bakery_id !== null), 403);
        }

        return $next($request);
    }
}
