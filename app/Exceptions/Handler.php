<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        // Sentry intégration si package présent
        $this->reportable(function (Throwable $e) {
            if (app()->bound('sentry') && $this->shouldReport($e)) {
                app('sentry')->captureException($e);
            }
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * Sprint 0 — Task 2 : all /api/* requests receive a unified JSON shape.
     * Non-API requests fall through to the default Laravel HTML renderer.
     */
    public function render($request, Throwable $e)
    {
        if ($json = ApiJsonRenderer::render($request, $e)) {
            return $json;
        }

        return parent::render($request, $e);
    }
}
