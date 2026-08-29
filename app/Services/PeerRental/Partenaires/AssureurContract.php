<?php

namespace App\Services\PeerRental\Partenaires;

use App\Models\PeerRental;

/**
 * L'ASSUREUR PARTENAIRE — LE CONTRAT, PAS ENCORE LE PARTENAIRE.
 *
 * Aucun assureur n'est contractualise a ce jour. Cette interface fixe ce que la plateforme
 * lui demandera ; `AssureurDeDemonstration` en tient la place et le DIT. Le jour ou un
 * contrat existe, une seule implementation s'ajoute et rien d'autre ne bouge.
 */
interface AssureurContract
{
    /**
     * Souscrire la formule choisie pour cette location.
     *
     * @return array{police: string|null, franchise_cents: int, actif: bool, source: string}
     */
    public function souscrire(PeerRental $location, string $formule): array;

    /** Resilier a l'annulation : une police souscrite pour une location qui n'a pas eu lieu. */
    public function resilier(PeerRental $location): bool;

    /** Declarer un sinistre constate au retour. */
    public function declarerUnSinistre(PeerRental $location, int $montantCents, string $description): ?string;

    /** Un partenaire reel est-il branche ? La reponse conditionne ce qu'on promet au locataire. */
    public function estOperationnel(): bool;
}
