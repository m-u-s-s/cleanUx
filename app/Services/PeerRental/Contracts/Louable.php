<?php

namespace App\Services\PeerRental\Contracts;

use App\Models\PeerVehicleAvailability;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * CE QU'UN BIEN DOIT SAVOIR DIRE POUR ÊTRE LOUÉ ENTRE MEMBRES.
 *
 * Une voiture et un logement n'ont presque rien en commun — l'une a une boîte de vitesses,
 * l'autre des chambres. Mais le CONTRAT de location est le même : un propriétaire, un prix par
 * jour, des remises à la durée, une caution, une durée plancher et plafond, une adresse, une
 * politique d'annulation, et le droit de réserver sans attendre une réponse.
 *
 * C'est ce contrat que la couche d'argent connaît — empreinte, caution, commission, versement,
 * avis, litiges. Elle ignore tout du reste, et c'est ce qui permet de ne l'écrire qu'une fois.
 */
interface Louable
{
    /** La clé du type, telle qu'elle sert à choisir le taux de commission. */
    public function typeDeBien(): string;

    public function proprietaire(): ?User;

    public function estPubliable(): bool;

    /** Le prix d'une journée, en centimes. */
    public function prixJournalierCents(): int;

    public function devise(): string;

    /** La remise consentie pour une durée, en pourcentage. */
    public function remisePourDuree(int $jours): int;

    public function cautionCents(): int;

    public function dureeMinimum(): int;

    public function dureeMaximum(): int;

    /** Le bien se réserve-t-il sans accord préalable du propriétaire ? */
    public function reservationInstantanee(): bool;

    public function politiqueDAnnulation(): string;

    /**
     * Les périodes pendant lesquelles le bien n'est pas disponible.
     *
     * @return MorphMany<PeerVehicleAvailability, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function indisponibilites(): MorphMany;
}
