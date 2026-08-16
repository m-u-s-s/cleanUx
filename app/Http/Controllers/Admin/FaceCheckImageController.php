<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProviderFaceCheck;
use App\Models\ProviderFaceProfile;
use App\Services\FaceCheck\FaceImageStore;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * LA SEULE FAÇON DE REGARDER UN VISAGE — et elle laisse une trace.
 *
 * Trois verrous, empruntés au contrôleur des pièces d'onboarding qui fait déjà exactement ça :
 * une URL SIGNÉE à durée courte, un contrôle d'administrateur en dur dans la méthode, et aucune
 * mise en cache. Sans l'URL signée, le chemin du fichier suffirait ; sans le contrôle en dur, une
 * signature qui fuite ouvrirait l'image à n'importe qui.
 *
 * S'y ajoute une quatrième chose que le contrôleur des pièces n'a pas : CHAQUE CONSULTATION EST
 * JOURNALISÉE. Une donnée biométrique regardée sans trace, c'est un registre de traitement qu'on
 * ne peut pas produire le jour où on le demande.
 */
class FaceCheckImageController extends Controller
{
    public function reference(Request $request, ProviderFaceProfile $profile, FaceImageStore $store): Response
    {
        $this->autoriser($request);

        $contenu = $store->get($profile->reference_path);

        $this->journaliser('face_check.reference_viewed', $profile->id, (int) $profile->user_id, $request);

        return $this->rendre($contenu, $profile->reference_mime);
    }

    public function selfie(Request $request, ProviderFaceCheck $faceCheck, FaceImageStore $store): Response
    {
        $this->autoriser($request);

        $contenu = $store->get($faceCheck->selfie_path);

        $this->journaliser('face_check.selfie_viewed', $faceCheck->id, (int) $faceCheck->user_id, $request);

        return $this->rendre($contenu, 'image/jpeg');
    }

    private function autoriser(Request $request): void
    {
        $utilisateur = $request->user();

        abort_unless($utilisateur !== null && $utilisateur->isPlatformAdmin(), 403);
    }

    private function rendre(?string $contenu, ?string $mime): Response
    {
        /*
         * 410 et non 404 : le selfie a existé, il a été purgé par la rétention. La nuance compte
         * pour l'administrateur qui enquête — « jamais eu d'image » et « image effacée après
         * trente jours » n'appellent pas la même conclusion.
         */
        abort_if($contenu === null, 410, "Cette image n'est plus disponible (purgée ou illisible).");

        return response($contenu, 200, [
            'Content-Type' => $mime ?: 'image/jpeg',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function journaliser(string $evenement, int $sujetId, int $prestataireId, Request $request): void
    {
        try {
            ActivityLogger::log($evenement, null, [
                'subject_id' => $sujetId,
                'provider_user_id' => $prestataireId,
                'admin_user_id' => $request->user()?->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
