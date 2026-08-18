<?php

use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureProjectNotLocked;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ThrottleVerificationAttempts;
use App\Http\Middleware\ThrottleVerificationResend;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function ($schedule) {
        $schedule->command('reminders:send')->everyMinute();
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'throttle.verification' => ThrottleVerificationResend::class,
            'throttle.verify' => ThrottleVerificationAttempts::class,
            'is.active' => EnsureUserIsActive::class,
            'verified' => EnsureEmailIsVerified::class,
            'project.not.locked' => EnsureProjectNotLocked::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        });
    })->create();
