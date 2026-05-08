<?php

namespace App\Modules\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user, Response::HTTP_UNAUTHORIZED);

        $isAllowed = collect($roles)->contains(function (string $role) use ($user) {
            return match ($role) {
                'super_admin', 'dev' => $user->isGlobalAdmin(),
                'collaborator' => ! $user->isGlobalAdmin(),
                'sector_admin' => $user->isSectorAdmin(),
                'technician' => $user->isTechnician(),
                'requester' => $user->isRequester(),
                default => false,
            };
        });

        abort_unless($isAllowed, Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
