<?php

namespace App\Http\Controllers\Messaging;

use App\Http\Controllers\Controller;
use App\Models\MessageAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Contrôleur pour la consultation des pièces jointes.
 *
 * Sécurité :
 *   - URL signée (URL::temporarySignedRoute) → expire en 15 min
 *   - Vérification que l'utilisateur est membre du channel du message
 *   - Refus si scan AV = infected
 */
class AttachmentDownloadController extends Controller
{
    public function download(Request $request, MessageAttachment $attachment): StreamedResponse
    {
        // Validation de la signature URL est faite par le middleware 'signed' sur la route

        $user = Auth::user();
        abort_if(! $user, 401);

        // Le user doit être membre du channel du message
        $message = $attachment->message;
        abort_if(! $message, 404);

        $isMember = $message->channel
            ->members()
            ->where('user_id', $user->id)
            ->exists();

        if (! $isMember && ! $user->isAdmin()) {
            abort(403, 'Accès refusé.');
        }

        if ($attachment->isInfected()) {
            abort(410, 'Ce fichier a été identifié comme dangereux et n\'est plus disponible.');
        }

        if (! $attachment->isReady()) {
            abort(425, 'Le fichier est encore en cours d\'analyse.');
        }

        $disk = Storage::disk($attachment->disk);
        if (! $disk->exists($attachment->path)) {
            abort(404, 'Fichier introuvable sur le storage.');
        }

        return $disk->download(
            $attachment->path,
            $attachment->original_name,
            [
                'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
            ]
        );
    }
}
