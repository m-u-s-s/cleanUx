<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /** Get the path the user should be redirected to when they are not authenticated. */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        // ENVOYER UNE WEBVIEW SUR `/login` LA LAISSE DEVANT UN FORMULAIRE QU'ELLE NE PEUT PAS
        // REMPLIR. L'application sait refaire la passation avec son jeton, mais seulement si la
        // page le lui dit : c'est `webview.session-expired` qui poste ce message au pont.
        if (EmbedMode::estEmbarque($request)) {
            return route('webview.session-expired');
        }

        return route('login');
    }
}
