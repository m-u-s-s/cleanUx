<?php

namespace App\Services\FaceCheck\Exceptions;

use App\Services\FaceCheck\Data\FaceCheckDecision;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as Routeur;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le refus du contrôle facial, levé depuis les services.
 *
 * ELLE ÉTEND `DomainException` DÉLIBÉRÉMENT. Les contrôleurs d'acceptation d'offre attrapent déjà
 * `\DomainException` et le traduisent en 409 avec le message : hériter d'elle garantit qu'aucun
 * chemin existant ne se met à rendre un 500 le jour où cette garde est posée. Les surfaces qui
 * savent quoi faire d'un `error_code` le lisent sur `decision` ; les autres continuent d'afficher
 * une phrase compréhensible, ce qui reste mieux que « une erreur est survenue ».
 *
 * `render()` couvre tout le reste : Laravel l'appelle automatiquement, donc aucun appelant n'a
 * besoin d'être modifié pour que le web redirige et que l'API réponde 403.
 */
class FaceCheckRequiredException extends DomainException
{
    public function __construct(public readonly FaceCheckDecision $decision)
    {
        parent::__construct($decision->message ?? "Un contrôle d'identité est nécessaire avant de continuer.");
    }

    public function render(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json($this->decision->toPayload(), 403);
        }

        $cible = Routeur::has('provider.face-check') ? 'provider.face-check' : 'home';

        return redirect()->route($cible)->with('warning', $this->getMessage());
    }
}
