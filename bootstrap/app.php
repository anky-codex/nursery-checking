<?php

use App\Exceptions\CheckInAlreadyClosed;
use App\Exceptions\ChildAlreadyCheckedIn;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: '', // endpoints are /rooms/..., not /api/rooms/...
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // This is a JSON-only API: never redirect or render HTML error pages.
        $exceptions->shouldRenderJsonWhen(fn () => true);

        // Expected business conflicts (HTTP 409) are not application errors.
        $exceptions->dontReport([
            ChildAlreadyCheckedIn::class,
            CheckInAlreadyClosed::class,
        ]);

        // Don't echo the internal model class name in 404 bodies.
        $exceptions->render(function (NotFoundHttpException $e) {
            $message = $e->getPrevious() instanceof ModelNotFoundException
                ? 'Resource not found.'
                : ($e->getMessage() !== '' ? $e->getMessage() : 'Not found.');

            return response()->json(['message' => $message], 404);
        });
    })->create();
