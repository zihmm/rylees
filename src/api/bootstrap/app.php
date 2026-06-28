<?php

declare(strict_types=1);

use App\Http\Middleware\AuthenticateWithApiKey;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
	    apiPrefix: ''
    )
    ->withMiddleware(function (Middleware $middleware): void
    {
        $middleware->api(append: [
            AuthenticateWithApiKey::class,
        ]);

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void
    {
		Integration::handles($exceptions);

        $exceptions->shouldRenderJsonWhen(fn (): bool => true);

        $exceptions->render(fn (AuthenticationException $e, Request $request) => response()->json([
            'message' => 'Unauthenticated.',
            'code' => 'unauthenticated',
        ], 401));

        $exceptions->render(fn (AuthorizationException $e, Request $request) => response()->json([
            'message' => 'Forbidden.',
            'code' => 'forbidden',
        ], 403));

        $exceptions->render(fn (ModelNotFoundException $e, Request $request) => response()->json([
            'message' => 'Resource not found.',
            'code' => 'not_found',
        ], 404));

        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => response()->json([
            'message' => 'Resource not found.',
            'code' => 'not_found',
        ], 404));

        $exceptions->render(fn (ValidationException $e, Request $request) => response()->json([
            'message' => 'The given data was invalid.',
            'code' => 'validation_error',
            'errors' => $e->errors(),
        ], 422));
    })->create();
