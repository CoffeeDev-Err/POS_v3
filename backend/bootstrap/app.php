<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);

        // Keep custom middleware aliases here so route files stay small and readable.
        $middleware->alias([
            'pos.auth' => \App\Http\Middleware\AuthenticateApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $throwable, $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            // API consumers should always get JSON errors, even for framework-level exceptions.
            $status = $throwable instanceof HttpExceptionInterface
                ? $throwable->getStatusCode()
                : 500;

            $message = $status >= 500
                ? 'Something went wrong on the server.'
                : ($throwable->getMessage() ?: 'Request failed.');

            return response()->json([
                'message' => $message,
            ], $status);
        });
    })->create();
