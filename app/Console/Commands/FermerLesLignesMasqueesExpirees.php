<?php

namespace App\Console\Commands;

use App\Services\Safety\MaskedCallService;
use Illuminate\Console\Command;

/** LIBÉRER LES NUMÉROS PROXY DONT LA MISSION EST FINIE. */
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
