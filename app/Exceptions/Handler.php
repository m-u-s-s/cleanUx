<?php

namespace App\Exceptions;

use App\Services\Cerveau\JournalDesIncidents;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
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

    /** Register the exception handling callbacks for the application. */
    public function register(): void
    {
        // Sentry intégration si package présent
        $this->reportable(function (Throwable $e) {
            if (app()->bound('sentry') && $this->shouldReport($e)) {
                app('sentry')->captureException($e);
            }
        });

        /*
         * LE JOURNAL DES INCIDENTS — le cerveau ne peut rien dire de ce qu'il ne voit pas.
         *
         * `shouldReport` ecarte deja ce qui n'est pas une panne : validation, 404, refus
         * d'authentification. Les enregistrer noierait les vrais defauts sous le bruit.
         *
         * Le journal AVALE ses propres erreurs : lever ici remplacerait le defaut d'origine
         * par le notre, et le rendrait invisible.
         */
        $this->reportable(function (Throwable $e) {
            if ($this->shouldReport($e)) {
                app(JournalDesIncidents::class)
                    ->enregistrer($e, auth()->id());
            }
        });
    }

    /** Render an exception into an HTTP response. */
    public function render($request, Throwable $e)
    {
        if ($json = ApiJsonRenderer::render($request, $e)) {
            return $json;
        }

        return parent::render($request, $e);
    }
}
