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
    ->withMiddleware(function (Middleware $middleware) {
        // Middleware global pour définir le contexte tenant
        $middleware->web(append: [
            \App\Http\Middleware\SetTenantContext::class,
        ]);
        
        $middleware->api(append: [
            \App\Http\Middleware\SetTenantContext::class,
        ]);
        
        // Alias pour les middlewares
        $middleware->alias([
            'verify.stripe.webhook' => \App\Http\Middleware\VerifyStripeWebhook::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'throttle.tenant' => \App\Http\Middleware\ThrottlePerTenant::class,
        ]);
    })
    ->withEvents(discover: [
        \App\Providers\EventServiceProvider::class,
    ])
    ->withProviders([
        \App\Providers\EventServiceProvider::class,
        \App\Providers\ModuleServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
