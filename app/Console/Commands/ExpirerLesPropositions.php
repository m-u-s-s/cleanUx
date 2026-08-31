<?php

namespace App\Console\Commands;

use App\Models\AutomationAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Depuis la tache 2, une proposition pendante gele son entite SANS fenetre : seule cette commande la degele. */
class ExpirerLesPropositions extends Command
{
    protected $signature = 'automation:expirer-les-propositions';

    protected $description = "Expire les propositions en attente d'une decision humaine depuis trop longtemps.";

    // 72 h laisse un week-end a l'administrateur avant de perdre la proposition ; au-dela, une
    // file oubliee gelerait l'entite bien plus longtemps que necessaire (contrepoids de la tache 2).
    public const DELAI_HEURES = 72;

    public function handle(): int
    {
        $limite = now()->subHours(self::DELAI_HEURES);

        $candidats = AutomationAction::query()
            ->where('resultat', AutomationAction::RESULTAT_PROPOSEE)
            ->where('pose_le', '<', $limite)
            ->pluck('id');

        $expirees = 0;

        foreach ($candidats as $id) {
            if ($this->expirer((int) $id)) {
                $expirees++;
            }
        }

        $this->info("{$expirees} proposition(s) expirée(s).");

        return self::SUCCESS;
    }

    /** Meme patron que FileDePropositions : verrou + controle sous verrou. Seul `resultat` s'y
     *  recontrole — `pose_le` ne s'ecrit qu'a la creation, le WHERE de la selection reste vrai. */
    protected function expirer(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            /** @var AutomationAction|null $ligne */
            $ligne = AutomationAction::query()->lockForUpdate()->find($id);

            if ($ligne === null || $ligne->resultat !== AutomationAction::RESULTAT_PROPOSEE) {
                return false;
            }

            // decide_par reste NUL : personne n'a tranche. motif et decide_le tracent quand
            // meme pourquoi et quand, pour que l'audit ne lise jamais une ligne muette.
            $ligne->forceFill([
                'resultat' => AutomationAction::RESULTAT_EXPIREE,
                'decide_le' => now(),
                'motif' => sprintf('Expirée automatiquement après %d h sans décision.', self::DELAI_HEURES),
            ])->save();

            return true;
        });
    }
}
