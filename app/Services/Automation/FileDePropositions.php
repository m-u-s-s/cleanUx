<?php

namespace App\Services\Automation;

use App\Models\AutomationAction;
use App\Models\User;
use App\Services\Automation\Registre\ActionRegistre;
use App\Services\Automation\Registre\EntiteRegistre;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Ce qu'un humain fait d'une proposition en attente : l'executer, ou la refuser. */
class FileDePropositions
{
    public function __construct(
        protected ActionRegistre $actions,
        protected EntiteRegistre $entites,
    ) {}

    /** @return Collection<int, AutomationAction> */
    public function enAttente(): Collection
    {
        return AutomationAction::query()
            ->where('resultat', AutomationAction::RESULTAT_PROPOSEE)
            ->orderBy('pose_le')
            ->get();
    }

    /** Valider EXECUTE maintenant et ecrit `validee` — jamais `executee`, c'est un humain qui
     *  tranche. Le verrou de ligne empeche une double decision sur la meme proposition. */
    public function valider(AutomationAction $ligne, User $par): ActionResult
    {
        return DB::transaction(function () use ($ligne, $par) {
            $verrouillee = $this->verrouiller($ligne);

            $verrouillee->forceFill(['decide_par' => $par->id, 'decide_le' => now()]);

            $resultat = $this->executerAction($verrouillee);

            // UN ECHEC A LA VALIDATION N'EST PAS UNE DECISION REUSSIE : `echouee`, comme le
            // moteur, pour degeler l'entite au prochain passage plutot que la figer a tort.
            $verrouillee->forceFill([
                'resultat' => $resultat->reussie ? AutomationAction::RESULTAT_VALIDEE : AutomationAction::RESULTAT_ECHOUEE,
                'message' => $resultat->message,
            ])->save();

            return $resultat;
        });
    }

    /** Refuser NE TOUCHE PAS a l'action : seule la ligne bouge, avec le motif d'un humain. */
    public function refuser(AutomationAction $ligne, User $par, string $motif): void
    {
        DB::transaction(function () use ($ligne, $par, $motif) {
            $verrouillee = $this->verrouiller($ligne);

            $verrouillee->forceFill([
                'resultat' => AutomationAction::RESULTAT_REFUSEE,
                'decide_par' => $par->id,
                'decide_le' => now(),
                'motif' => $motif,
            ])->save();
        });
    }

    /** Le verrou (`SELECT ... FOR UPDATE`) rend la decision atomique : deux admins qui valident
     *  la meme ligne en meme temps ne peuvent pas tous les deux la gagner. */
    protected function verrouiller(AutomationAction $ligne): AutomationAction
    {
        /** @var AutomationAction $verrouillee */
        $verrouillee = AutomationAction::query()->lockForUpdate()->findOrFail($ligne->getKey());

        if ($verrouillee->resultat !== AutomationAction::RESULTAT_PROPOSEE) {
            throw new DecisionDejaPrise("La proposition #{$verrouillee->id} a déjà été décidée.");
        }

        return $verrouillee;
    }

    /** Memes gardes que `RuleRunner::poser()` : action inconnue et exception sont des echecs. */
    protected function executerAction(AutomationAction $ligne): ActionResult
    {
        $action = $this->actions->trouver($ligne->action_cle);

        if ($action === null) {
            return ActionResult::echouee("Action inconnue : {$ligne->action_cle}");
        }

        $entite = $this->entites->descripteur($ligne->entite_type)?->baseQuery()->find($ligne->entite_id);

        if ($entite === null) {
            return ActionResult::echouee("Entité introuvable : {$ligne->entite_type} #{$ligne->entite_id}");
        }

        try {
            return $action->executer($entite, $ligne->parametres ?? []);
        } catch (Throwable $e) {
            return ActionResult::echouee(mb_substr($e->getMessage(), 0, 250));
        }
    }
}
