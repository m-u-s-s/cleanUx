<?php

namespace App\Console\Commands;

use App\Services\FaceCheck\FaceCheckService;
use Illuminate\Console\Command;

/**
 * L'ENTRETIEN DU CONTRÔLE FACIAL — ce que le temps doit faire tout seul.
 *
 * Une seule chose ici : fermer les contrôles restés ouverts. Le prestataire a ouvert l'écran,
 * puis le téléphone est parti en poche. Sans cette commande, ce contrôle resterait `pending`
 * indéfiniment, et la porte lirait « contrôle déjà ouvert » sans jamais en rouvrir un — le
 * prestataire serait coincé devant un écran mort.
 *
 * LA PURGE DES SELFIES N'EST PAS ICI. Elle vit dans `RetentionPolicyService`, avec les six autres
 * rétentions de la plateforme, et tourne avec `gdpr:enforce-retention`. Une seconde purge ici
 * ferait deux endroits qui décident de la même durée — et le jour où l'un des deux change, plus
 * personne ne sait lequel fait foi.
 */
class FaceCheckMaintenance extends Command
{
    protected $signature = 'face-check:maintenance';

    protected $description = 'Ferme les contrôles faciaux restés ouverts au-delà de leur délai.';

    public function handle(FaceCheckService $service): int
    {
        $expires = $service->expireStale();

        $this->info("Contrôles faciaux expirés : {$expires}");

        return self::SUCCESS;
    }
}
