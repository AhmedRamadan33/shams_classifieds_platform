<?php

use App\Http\Middleware\Api\EnsureApiUserIsNotBanned;
use App\Http\Middleware\EnsurePhoneIsVerified;
use App\Http\Middleware\EnsureUserIsNotBanned;
use App\Http\Middleware\RejectHoneypot;
use App\Http\Middleware\SecurityHeaders;
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
        $middleware->alias([
            'phone.verified' => EnsurePhoneIsVerified::class,
            'honeypot' => RejectHoneypot::class,
            'api.not-banned' => EnsureApiUserIsNotBanned::class,
        ]);

        $middleware->appendToGroup('web', EnsureUserIsNotBanned::class);

        $middleware->append(SecurityHeaders::class);

        $middleware->validateCsrfTokens(except: ['payments/webhook/paymob']);
    })
    ->withExceptions(function (Exceptions $exceptions) {})->create();
