<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProviderFaceCheck;
use App\Models\ProviderFaceProfile;
use App\Services\FaceCheck\FaceImageStore;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** LA SEULE FAÇON DE REGARDER UN VISAGE — et elle laisse une trace. */
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
        // 410 et non 404 : le selfie a existé, il a été purgé par la rétention.
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
