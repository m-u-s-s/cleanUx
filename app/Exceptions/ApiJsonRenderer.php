<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Sprint 0 — Task 2 : JSON exception handler unifié pour l'API mobile.
 *
 * Tous les appels sur /api/* (ou expectsJson) reçoivent un format unifié :
 *   { ok: false, error_code, message, errors? }
 *
 * En mode debug (APP_DEBUG=true), les 500 embarquent un champ `debug`
 * avec le nom de la classe et le fichier:ligne — jamais en production.
 */
class ApiJsonRenderer
{
    public static function render(Request $request, Throwable $e): ?JsonResponse
    {
        if (!self::shouldRender($request)) {
            return null;
        }

        [$status, $code, $message, $errors] = self::resolve($e);

        $payload = ['ok' => false, 'error_code' => $code, 'message' => $message];
        if ($errors !== null) {
            $payload['errors'] = $errors;
        }
        if (config('app.debug') && $status >= 500) {
            $payload['debug'] = [
                'exception' => get_class($e),
                'file'      => $e->getFile() . ':' . $e->getLine(),
            ];
        }

        return response()->json($payload, $status);
    }

    private static function shouldRender(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    private static function resolve(Throwable $e): array
    {
        if ($e instanceof ValidationException) {
            return [422, 'validation_failed', $e->getMessage(), $e->errors()];
        }
        if ($e instanceof AuthenticationException) {
            return [401, 'unauthenticated', 'Authentication required.', null];
        }
        if ($e instanceof AuthorizationException) {
            return [403, 'forbidden', $e->getMessage() ?: 'Forbidden.', null];
        }
        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return [404, 'not_found', 'Resource not found.', null];
        }
        if ($e instanceof TooManyRequestsHttpException) {
            return [429, 'rate_limited', 'Too many requests.', null];
        }
        if ($e instanceof AccessDeniedHttpException) {
            return [403, 'forbidden', $e->getMessage() ?: 'Access denied.', null];
        }
        if ($e instanceof HttpException) {
            return [$e->getStatusCode(), 'http_error', $e->getMessage() ?: 'HTTP error.', null];
        }
        return [500, 'server_error', 'An unexpected error occurred.', null];
    }
}
