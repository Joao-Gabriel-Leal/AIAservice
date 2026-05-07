<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChangeIsCompleted
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->must_change_password || $this->isAllowedWhilePasswordChangeIsPending($request)) {
            return $next($request);
        }

        return redirect()->route('password.force-change');
    }

    private function isAllowedWhilePasswordChangeIsPending(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        if ($routeName && Str::is([
            'login',
            'login.store',
            'logout',
            'password.*',
            'two-factor.*',
            'livewire.*',
            'default-livewire.*',
        ], $routeName)) {
            return true;
        }

        return $request->is('livewire/*') || $request->is('livewire-*');
    }
}
