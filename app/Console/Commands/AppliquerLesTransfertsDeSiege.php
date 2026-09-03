<?php

namespace App\Console\Commands;

use App\Services\Platform\SiegeDuSuperAdmin;
use Illuminate\Console\Command;

/**
 * LA PASSE QUI REND LES TRANSFERTS EFFECTIFS.
 *
 * Un transfert s'arme, il ne s'applique pas : c'est ce délai qui laisse au titulaire le temps
 * d'annuler. Sans cette passe, aucun transfert n'aboutirait jamais — le délai serait un refus
 * déguisé, et le siège ne pourrait plus changer de mains.
 */
class AppliquerLesTransfertsDeSiege extends Command
{
    protected $signature = 'plateforme:siege-appliquer';

    protected $description = 'Applique les transferts de siège dont le délai est écoulé.';

    public function handle(SiegeDuSuperAdmin $siege): int
    {
        $appliques = $siege->appliquerLesTransfertsMurs();

        if ($appliques > 0) {
            $this->info($appliques.' transfert(s) de siège appliqué(s).');
        }

        return self::SUCCESS;
    }
}
