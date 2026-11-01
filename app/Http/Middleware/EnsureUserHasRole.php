<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless(
            in_array($request->user()?->role?->value, $roles, true),
            Response::HTTP_FORBIDDEN,
            'Anda tidak memiliki akses ke halaman ini.',
        );

        return $next($request);
    }
}
