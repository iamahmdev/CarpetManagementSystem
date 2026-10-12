<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            $status = $exception->getStatusCode();

            if ($request->expectsJson() || ! in_array($status, [403, 404], true)) {
                return null;
            }

            return Inertia::render('errors/page', [
                'status' => $status,
                'role' => $request->user()?->role?->value
                    ? Str::headline($request->user()->role->value)
                    : null,
                'pageLabel' => match (true) {
                    str_starts_with($request->path(), 'users') => 'Users and Roles',
                    str_starts_with($request->path(), 'carpets') => 'Carpets',
                    str_starts_with($request->path(), 'pieces') => 'Pieces',
                    str_starts_with($request->path(), 'containers') => 'Containers',
                    str_starts_with($request->path(), 'transfers') => 'Transfers',
                    str_starts_with($request->path(), 'sales') => 'Sales History',
                    str_starts_with($request->path(), 'stock-report') => 'Stock by Location',
                    default => Str::headline(basename($request->path())),
                },
            ])->toResponse($request)->setStatusCode($status);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
