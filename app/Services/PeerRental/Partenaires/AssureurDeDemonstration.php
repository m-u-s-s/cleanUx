<?php

namespace App\Services\PeerRental\Partenaires;

use App\Models\PeerRental;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * LA COQUILLE, ET ELLE NE MENT PAS.
 *
 * Elle repond a l'interface pour que les ecrans et les tests existent, mais `estOperationnel`
 * rend FAUX : aucune couverture n'est reellement souscrite. C'est ce booleen que l'interface
 * consulte avant d'annoncer quoi que ce soit au locataire — une protection promise et non
 * souscrite serait pire que pas de protection du tout.
 */
class AssureurDeDemonstration implements AssureurContract
{
    public function souscrire(PeerRental $location, string $formule): array
    {
        /** @var array<string, array{label: string, daily_cents: int, franchise_cents: int}> $formules */
        $formules = config('peer_rental.insurance.plans', []);

        Log::info('Souscription d’assurance simulée', [
            'peer_rental' => $location->reference,
            'formule' => $formule,
        ]);

        return [
            'police' => 'DEMO-'.Str::upper(Str::random(8)),
            'franchise_cents' => (int) ($formules[$formule]['franchise_cents'] ?? 0),
            'actif' => false,
            'source' => 'demonstration',
        ];
    }

    public function resilier(PeerRental $location): bool
    {
        return true;
    }

    public function declarerUnSinistre(PeerRental $location, int $montantCents, string $description): ?string
    {
        Log::info('Sinistre simulé', [
            'peer_rental' => $location->reference,
            'montant_cents' => $montantCents,
        ]);

        return null;
    }

    public function estOperationnel(): bool
    {
        return false;
    }
}
