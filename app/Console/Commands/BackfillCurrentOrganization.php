<?php

namespace App\Console\Commands;

use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * RATTRAPAGE : DONNER UNE ORGANISATION COURANTE AUX MEMBRES QUI N'EN ONT PAS.
 *
 * `EnsureOrganizationType` — la garde des deux espaces société — lit
 * `users.current_organization_id`. Appartenir activement à une organisation ne suffit donc pas :
 * sans ce champ, la porte reste fermée, et l'utilisateur ne voit aucun écran de sa propre société.
 *
 * Constaté en base avant écriture : 7 membres actifs pour 1 seul utilisateur pourvu d'une
 * organisation courante.
 *
 * CE N'EST PAS UN CORRECTIF, C'EST UN RATTRAPAGE. `OrganizationMembershipService`, écrit en
 * phase 0, renseigne bien le champ à l'adhésion : le code actuel ne produit plus ce cas. Restent
 * les comptes créés AVANT, quand rien ne le faisait.
 *
 * DEUX PRUDENCES :
 *
 *   - Simulation par défaut. Écrire sur la table des utilisateurs sans avoir vu ce qui va changer
 *     est le genre de geste qu'on regrette ; `--apply` est explicite.
 *   - Une seule organisation, sinon rien. Un membre actif de PLUSIEURS sociétés n'a pas
 *     d'organisation courante évidente ; en choisir une le placerait quelque part au hasard, et il
 *     verrait les données d'une société qu'il n'a pas demandée. On le signale et on passe.
 */
class BackfillCurrentOrganization extends Command
{
    protected $signature = 'organizations:backfill-current {--apply : Écrire réellement les changements}';

    protected $description = "Donne une organisation courante aux membres actifs qui n'en ont pas";

    public function handle(): int
    {
        $applique = (bool) $this->option('apply');

        if (! $applique) {
            $this->warn('SIMULATION — aucune écriture. Relancer avec --apply pour appliquer.');
        }

        $candidats = User::query()
            ->whereNull('current_organization_id')
            ->whereHas('organizationMemberships', fn ($q) => $q->where('status', 'active'))
            ->get(['id', 'email']);

        if ($candidats->isEmpty()) {
            $this->info('0 utilisateur à rattraper.');

            return self::SUCCESS;
        }

        $rattaches = 0;
        $ambigus = [];

        foreach ($candidats as $utilisateur) {
            $organisations = OrganizationMember::query()
                ->where('user_id', $utilisateur->id)
                ->where('status', 'active')
                ->pluck('organization_account_id')
                ->unique()
                ->values();

            if ($organisations->count() !== 1) {
                $ambigus[] = sprintf('%s (%d organisations)', $utilisateur->email, $organisations->count());

                continue;
            }

            $orgId = (int) $organisations->first();

            $this->line(sprintf('  %s → organisation #%d', $utilisateur->email, $orgId));

            if ($applique) {
                // `forceFill` plutôt que `update` : `current_organization_id` est assignable en
                // masse, mais on ne veut ici toucher QUE ce champ, sans dépendre du fillable.
                $utilisateur->forceFill(['current_organization_id' => $orgId])->save();
            }

            $rattaches++;
        }

        $this->info(sprintf(
            '%d utilisateur(s) %s.',
            $rattaches,
            $applique ? 'rattaché(s)' : 'seraient rattaché(s)'
        ));

        if ($ambigus !== []) {
            $this->warn(sprintf(
                '%d utilisateur(s) laissé(s) intact(s), membres de plusieurs organisations :',
                count($ambigus)
            ));

            foreach ($ambigus as $ligne) {
                $this->line('  '.$ligne);
            }

            $this->line('Ils doivent choisir leur espace eux-mêmes — la commande ne tranche pas à leur place.');
        }

        return self::SUCCESS;
    }
}
