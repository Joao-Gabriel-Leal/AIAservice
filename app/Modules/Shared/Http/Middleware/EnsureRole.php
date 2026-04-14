<?php

namespace App\Modules\Shared\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user, Response::HTTP_UNAUTHORIZED);

        $allowed = collect($roles)
            ->map(fn (string $role) => UserRole::from($role))
            ->all();

        abort_unless(in_array($user->role, $allowed, true), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
