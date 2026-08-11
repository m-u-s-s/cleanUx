<?php

namespace App\Console\Commands;

use App\Services\Safety\MaskedCallService;
use Illuminate\Console\Command;

/**
 * LIBÉRER LES NUMÉROS PROXY DONT LA MISSION EST FINIE.
 *
 * `MaskedCallService::scanExpired()` existait depuis l'écriture du module et n'était appelé par
 * RIEN — ni commande, ni planificateur, ni contrôleur. Les sessions restaient donc ouvertes
 * indéfiniment.
 *
 * DEUX CONSÉQUENCES, ET LA SECONDE EST LA PLUS GÊNANTE. Chaque numéro proxy est loué au fournisseur
 * et se paie tant qu'il est réservé : un stock qui ne se libère jamais coûte tous les mois. Mais
 * surtout, une ligne qui reste ouverte permet à un prestataire de rappeler une cliente des semaines
 * après l'intervention — exactement ce que le masquage était censé empêcher.
 *
 * Toutes les heures suffit : la durée de vie d'une session se compte en heures après la fin de
 * mission, pas en minutes.
 */
class FermerLesLignesMasqueesExpirees extends Command
{
    protected $signature = 'masked-calls:scan-expired';

    protected $description = 'Ferme les sessions d’appel masqué arrivées à expiration et libère leurs numéros.';

    public function handle(MaskedCallService $service): int
    {
        $fermees = $service->scanExpired();

        $this->info($fermees === 0
            ? 'Aucune ligne masquée à fermer.'
            : $fermees.' ligne(s) masquée(s) fermée(s).');

        return self::SUCCESS;
    }
}
