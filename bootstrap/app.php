<?php

use App\Http\Middleware\EnsureActiveUserSession;
use App\Http\Middleware\EnsurePasswordChangeIsCompleted;
use App\Modules\Shared\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => EnsureRole::class,
        ]);

        $middleware->web(append: [
            EnsureActiveUserSession::class,
            EnsurePasswordChangeIsCompleted::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if (
                $response->getStatusCode() === 419
                && $exception->getPrevious() instanceof TokenMismatchException
                && $request->isMethod('POST')
                && $request->is('logout')
                && ! $request->user()
            ) {
                return redirect()->route('login');
            }

            return $response;
        });
    })->create();
