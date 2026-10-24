<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToPriorityList(
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \App\Http\Middleware\InfrastructureHeaders::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (\Illuminate\Http\Request $request, Throwable $e): bool => $request->is('api/*') || $request->expectsJson());

        $exceptions->render(function (Throwable $e, \Illuminate\Http\Request $request) {
            if (($request->is('api/v1/*') || $request->is('api/mcp'))
                && ! $e instanceof \Illuminate\Validation\ValidationException
                && ! $e instanceof \Illuminate\Auth\Access\AuthorizationException
                && ! $e instanceof \Illuminate\Auth\AuthenticationException
                && ! $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException
                && ! $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                return response()->json(['message' => 'Erreur interne. Consultez les logs du serveur.'], 500);
            }
        });

        $exceptions->report(function (Throwable $e) {

            Log::error($e->getMessage(), [
                'exception' => $e,
            ]);

        });
    })->create();
