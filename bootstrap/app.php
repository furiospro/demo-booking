<?php

use App\Exceptions\BookingConflictException;
use App\Exceptions\BookingInvalidIntervalException;
use App\Exceptions\BookingOutsideWorkingHoursException;
use App\Http\Middleware\AssignRequestId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AssignRequestId::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        
        $exceptions->renderable(function (BookingConflictException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        });

        $exceptions->renderable(function (BookingOutsideWorkingHoursException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        });

        $exceptions->renderable(function (BookingInvalidIntervalException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        });
    })->create();
