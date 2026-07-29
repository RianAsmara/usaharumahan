<?php

use App\Http\Middleware\AddRequestId;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireTenant;
use App\Http\Middleware\ResolveTenantForDashboard;
use App\Http\Middleware\ResolveTenantFromHostname;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AddRequestId::class);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            // Runs on every web request: resolves the Host header to a
            // tenant when it matches one, and simply falls through
            // otherwise (see the class docblock for why this one is soft).
            ResolveTenantFromHostname::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'tenant.required' => RequireTenant::class,
            'tenant.dashboard' => ResolveTenantForDashboard::class,
            'platform.admin' => EnsurePlatformAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // AddRequestId's own header write never runs for thrown exceptions: the
        // exception unwinds past its post-$next() code straight to the handler
        // below. Attach the header here too so error responses stay traceable.
        $exceptions->respond(function (Response $response) {
            if ($requestId = Context::get('request_id')) {
                $response->headers->set('X-Request-Id', $requestId);
            }

            return $response;
        });
    })->create();
