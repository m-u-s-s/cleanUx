<?php

namespace App\Services\FaceCheck\Exceptions;

use App\Services\FaceCheck\Data\FaceCheckDecision;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as Routeur;
use Symfony\Component\HttpFoundation\Response;

/** Le refus du contrôle facial, levé depuis les services. ELLE ÉTEND `DomainException` DÉLIBÉRÉMENT. */
class FaceCheckRequiredException extends DomainException
{
    public function __construct(public readonly FaceCheckDecision $decision)
    {
        parent::__construct($decision->message ?? __('face_check.errors.default'));
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
