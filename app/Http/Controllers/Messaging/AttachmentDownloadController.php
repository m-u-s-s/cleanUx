<?php

namespace App\Http\Controllers\Messaging;

use App\Http\Controllers\Controller;
use App\Models\MessageAttachment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Contrôleur pour la consultation des pièces jointes. */
class AttachmentDownloadController extends Controller
{
    public function download(Request $request, MessageAttachment $attachment): StreamedResponse
    {
        // Validation de la signature URL est faite par le middleware 'signed' sur la route

        /*
         * DEUX FACONS DE NOMMER LE LECTEUR, UNE SEULE AUTORISATION.
         *
         * La session quand elle existe ; sinon l'identifiant que porte l'URL. Le lire dans la
         * requete n'est sur QUE parce que le middleware `signed` a deja valide la chaine
         * entiere : substituer un lecteur invaliderait la signature.
         *
         * La verification d'appartenance au canal, elle, ne change pas.
         */
        $user = Auth::user();

        if (! $user && $request->integer('viewer') > 0) {
            $user = User::find($request->integer('viewer'));
        }

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
