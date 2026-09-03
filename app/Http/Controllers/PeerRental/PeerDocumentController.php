<?php

namespace App\Http\Controllers\PeerRental;

use App\Http\Controllers\Controller;
use App\Models\PeerVehicleDocument;
use App\Services\PeerRental\Contracts\Louable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * OUVRIR UN PAPIER DEPOSE.
 *
 * Valider un document qu'on ne peut pas lire n'est pas une vérification, c'est une signature à
 * l'aveugle. Ces fichiers vivent sur le disque PRIVÉ — un titre de propriété porte un nom, une
 * adresse et parfois un numéro national — donc ils ne peuvent pas sortir par une URL de stockage.
 */
class PeerDocumentController extends Controller
{
    public function __invoke(Request $request, PeerVehicleDocument $document): StreamedResponse
    {
        $bien = $document->documentable ?? $document->vehicle;
        $proprietaire = $bien instanceof Louable ? $bien->proprietaire() : null;

        // DEUX PORTES, PAS UNE : celui qui a déposé le papier, et celui qui doit l'examiner.
        abort_unless(
            ($proprietaire !== null && $proprietaire->id === $request->user()?->id)
                || Gate::allows('manage-peer-rentals'),
            403
        );

        $chemin = (string) $document->file_path;

        abort_unless($chemin !== '' && Storage::disk('local')->exists($chemin), 404);

        return Storage::disk('local')->response(
            $chemin,
            $document->file_name ?: basename($chemin),
            ['Content-Type' => $document->mime_type ?: 'application/octet-stream'],
        );
    }
}
