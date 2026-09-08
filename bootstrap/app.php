<?php

use App\Exceptions\AppException;
use App\Exceptions\Domain\FileUploadExceededException;
use App\Http\Middleware\CheckUploadLimits;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->web(append: [
            CheckUploadLimits::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);

        $middleware->trustProxies(at: [
            '172.21.4.2',
            '172.19.16.105',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->reportable(fn (AppException $e) => $e->shouldReport());

        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            $uploadException = FileUploadExceededException::fromIniLimits(previous: $e);

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'message' => $uploadException->getMessage(),
                    'code' => class_basename($uploadException),
                ], $uploadException->getHttpStatus());
            }

            return back()->withErrors(['message' => $uploadException->getMessage()]);
        });

        $exceptions->render(function (AppException $e, Request $request) {
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'code' => class_basename($e),
                ], $e->getHttpStatus());
            }

            return back()->withErrors(['message' => $e->getMessage()]);
        });

        $exceptions->render(function (InvalidArgumentException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        });
        Integration::handles($exceptions);
    })->create();
