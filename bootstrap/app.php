<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['data' => null, 'meta' => [], 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Authentication is required.']], 401);
            }
        });
        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['data' => null, 'meta' => [], 'error' => ['code' => 'DATA_NOT_FOUND', 'message' => 'The requested resource was not found.']], 404);
            }
        });
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['data' => null, 'meta' => [], 'error' => ['code' => 'VALIDATION_FAILED', 'message' => 'The submitted data is invalid.', 'fields' => $exception->errors()]], 422);
            }
        });
    })->create();
