<?php

namespace App\Services\PeerRental\Partenaires;

use App\Models\PeerVehicle;

/**
 * LA COQUILLE, ET ELLE NE MENT PAS.
 *
 * Elle ne rend AUCUN releve — `null` partout — et `estOperationnel` vaut faux. Rendre un
 * kilometrage invente serait pire que n'en rendre aucun : il servirait a facturer.
 */
class TelematiqueDeDemonstration implements TelematiqueContract
{
    public function kilometrage(PeerVehicle $vehicule): ?int
    {
        return null;
    }

    public function carburantEnHuitiemes(PeerVehicle $vehicule): ?int
    {
        return null;
    }

    public function deverrouiller(PeerVehicle $vehicule): bool
    {
        return false;
    }

    public function verrouiller(PeerVehicle $vehicule): bool
    {
        return false;
    }

    public function estOperationnel(): bool
    {
        return false;
    }
}
