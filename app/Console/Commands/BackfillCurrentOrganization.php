<?php

namespace App\Console\Commands;

use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Console\Command;

/** RATTRAPAGE : DONNER UNE ORGANISATION COURANTE AUX MEMBRES QUI N'EN ONT PAS. */
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
