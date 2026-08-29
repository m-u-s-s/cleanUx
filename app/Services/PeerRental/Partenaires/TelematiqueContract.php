<?php

namespace App\Services\PeerRental\Partenaires;

use App\Models\PeerVehicle;

/**
 * LE BOITIER TELEMATIQUE — LE CONTRAT, PAS ENCORE LE BOITIER.
 *
 * Aucun fournisseur n'est contractualise. Les colonnes `telematics_provider` et
 * `telematics_device_id` du vehicule attendent ce jour-la ; d'ici la, la remise des cles
 * passe par le code a six chiffres, qui ne depend d'aucun materiel.
 */
interface TelematiqueContract
{
    /** Le kilometrage releve par le boitier, ou null s'il ne repond pas. */
    public function kilometrage(PeerVehicle $vehicule): ?int;

    /** Le niveau de carburant, en huitiemes. */
    public function carburantEnHuitiemes(PeerVehicle $vehicule): ?int;

    /** Deverrouiller a distance, a la place de la remise de cles physique. */
    public function deverrouiller(PeerVehicle $vehicule): bool;

    public function verrouiller(PeerVehicle $vehicule): bool;

    /** Un boitier reel est-il branche ? */
    public function estOperationnel(): bool;
}
