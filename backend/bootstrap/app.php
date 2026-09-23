<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

// The Hostinger bundle is served by public_html/api/index.php. Its web server
// removes that directory from PATH_INFO, so Laravel must register `/login`
// rather than `/api/login`. A marker keeps HTTP requests and `route:cache`
// consistent; relying only on .env here is unsafe because this file runs before
// Laravel loads .env during CLI commands.
$hostedFromApiSubdirectory = is_file(dirname(__DIR__).'/.hostinger-api-subdirectory');
$apiPrefix = $hostedFromApiSubdirectory
    ? ''
    : (array_key_exists('API_ROUTE_PREFIX', $_ENV)
        ? $_ENV['API_ROUTE_PREFIX']
        : (array_key_exists('API_ROUTE_PREFIX', $_SERVER) ? $_SERVER['API_ROUTE_PREFIX'] : 'api'));

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: $apiPrefix,
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);

        // Keep custom middleware aliases here so route files stay small and readable.
        $middleware->alias([
            'pos.auth' => \App\Http\Middleware\AuthenticateApiToken::class,
            'pos.role' => \App\Http\Middleware\RequireApiRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $throwable, $request) {
            if (!$request->expectsJson() && !$request->is('api/*')) {
                return null;
            }

            if ($throwable instanceof ValidationException) {
                $errors = $throwable->errors();
                $firstFieldErrors = reset($errors);
                $message = is_array($firstFieldErrors) && isset($firstFieldErrors[0])
                    ? $firstFieldErrors[0]
                    : 'The provided data is invalid.';

                return response()->json([
                    'message' => $message,
                    'errors' => $errors,
                ], 422);
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
