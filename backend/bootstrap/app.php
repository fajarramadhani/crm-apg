<?php

use App\Exceptions\IdempotencyConflict;
use App\Exceptions\InvalidTicketTransition;
use App\Exceptions\KnowledgeBaseConflict;
use App\Exceptions\PublicActionAccessDenied;
use App\Exceptions\PublicHistoryAccessDenied;
use App\Http\Middleware\AddPublicTrackingHeaders;
use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->append(AddSecurityHeaders::class);
        $middleware->statefulApi();
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'role' => EnsureUserHasRole::class,
            'permission' => EnsureUserHasPermission::class,
            'public_tracking_headers' => AddPublicTrackingHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::validationError($request, $exception->errors());
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error($request, 'Unauthenticated.', 'UNAUTHENTICATED', 401);
        });

        $exceptions->render(function (PublicHistoryAccessDenied $exception, Request $request) {
            if (! $request->is('api/v1/public/ticket-history*')) {
                return null;
            }

            return ApiResponse::error($request, 'Public history access is invalid or expired.', 'INVALID_ACCESS', 401);
        });

        $exceptions->render(function (PublicActionAccessDenied $exception, Request $request) {
            if (! $request->is('api/v1/public/tickets/track/*')) {
                return null;
            }

            return ApiResponse::error($request, 'Public ticket action access is invalid or expired.', 'INVALID_ACTION_ACCESS', 401);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error($request, 'You are not authorized to access this resource.', 'FORBIDDEN', 403);
        });

        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error($request, 'You are not authorized to access this resource.', 'FORBIDDEN', 403);
        });

        $exceptions->render(function (InvalidTicketTransition $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error($request, $exception->getMessage(), 'INVALID_TRANSITION', 409, ['current_status' => $exception->currentStatus]);
        });

        $exceptions->render(function (IdempotencyConflict $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error($request, $exception->getMessage(), 'IDEMPOTENCY_CONFLICT', 409, [
                'reason' => $exception->reason,
            ]);
        });

        $exceptions->render(function (KnowledgeBaseConflict $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $details = $exception->currentStatus === null ? [] : ['current_status' => $exception->currentStatus];

            return ApiResponse::error($request, $exception->getMessage(), 'KNOWLEDGE_BASE_CONFLICT', 409, $details);
        });

        $exceptions->render(function (TooManyRequestsHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $message = $request->is('api/v1/public/ticket-history*') || $request->is('api/v1/public/tickets/track/*')
                ? 'Too many requests. Please try again later.'
                : ($request->is('api/v1/public/tickets')
                ? 'Too many ticket submissions. Please try again later.'
                : 'Terlalu banyak percobaan login. Silakan coba lagi nanti.');

            return ApiResponse::error($request, $message, 'TOO_MANY_ATTEMPTS', 429);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            if (
                $exception instanceof MethodNotAllowedHttpException ||
                $exception instanceof NotFoundHttpException ||
                $exception instanceof AccessDeniedHttpException
            ) {
                return null;
            }
            if ($exception->getStatusCode() === 419) {
                return ApiResponse::error($request, 'CSRF token mismatch.', 'CSRF_TOKEN_MISMATCH', 419);
            }

            $messages = [
                400 => 'Bad request',
                409 => 'Request conflicts with the current resource state',
                422 => 'Request could not be processed',
                503 => 'Service unavailable',
            ];

            return ApiResponse::error($request, $messages[$exception->getStatusCode()] ?? 'Request failed', 'HTTP_ERROR', $exception->getStatusCode());
        });

        $exceptions->render(function (MethodNotAllowedHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error($request, 'Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error($request, 'Resource not found', 'NOT_FOUND', 404);
        });

        $exceptions->render(function (QueryException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error($request, 'Database operation failed', 'DATABASE_ERROR', 503);
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $context = ['request_id' => $request->attributes->get('request_id')];
            if ($request->is('api/v1/public/tickets/track/*')) {
                $context['exception_class'] = $exception::class;
            } else {
                $context['exception'] = $exception;
            }
            Log::error('Unhandled API exception', $context);

            return ApiResponse::error($request, 'An unexpected error occurred', 'SERVER_ERROR', 500);
        });
    })->create();
