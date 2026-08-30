<?php

namespace App\Services\Automation;

use App\Models\AutomationAction as LigneDeJournal;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Services\Automation\Registre\ActionRegistre;
use App\Services\Automation\Registre\EntiteRegistre;
use App\Services\Conditions\RuleTreeEvaluator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/** Evalue une regle en UNE requete, pose ses actions, et journalise tout ce qu'il fait. */
class RuleRunner
{
    public function __construct(
        protected EntiteRegistre $entites,
        protected ActionRegistre $actions,
        protected RuleTreeEvaluator $evaluateur,
    ) {}

    /** @param list<int>|null $identifiants restreint le balayage, pour le drain d'evenements */
    public function executer(AutomationRule $regle, ?array $identifiants = null): AutomationRun
    {
        $observation = $regle->etat === AutomationRule::ETAT_OBSERVATION;

        $passage = AutomationRun::create([
            'automation_rule_id' => $regle->id,
            'mode' => $observation ? 'observation' : 'armee',
            'demarre_le' => now(),
        ]);

        $entite = $this->entites->descripteur($regle->entite);

        if ($entite === null) {
            $passage->forceFill([
                'statut' => 'echec',
                'message' => "Entité inconnue : {$regle->entite}",
                'termine_le' => now(),
            ])->save();

            return $passage;
        }

        $requete = $entite->baseQuery();
        $this->evaluateur->apply($requete, $regle->conditions ?? [], $entite);

        if ($identifiants !== null) {
            $requete->whereKey($identifiants);
        }

        $this->exclureLeDejaAgi($requete, $regle);

        // LE +1 EST LE SIGNAL : sans lui, « exactement le quota » et « mille » sont
        // indiscernables, et l'emballement ne se voit jamais.
        $restantAujourdhui = max(0, $regle->plafond_journalier - $this->poseesAujourdhui($regle));
        $quota = min($regle->quota_par_passage, $restantAujourdhui);

        $lignes = $requete->limit($quota + 1)->get();
        $bride = $lignes->count() > $quota;
        $lignes = $lignes->take($quota);
        $posees = 0;

        foreach ($lignes as $ligne) {
            foreach (($regle->actions ?? []) as $demande) {
                $this->poser($regle, $passage, $ligne, (array) $demande, $observation);
                $posees++;
            }
        }

        $passage->forceFill([
            'entites_vues' => $lignes->count(),
            'actions_posees' => $posees,
            'statut' => $bride ? 'plafond_atteint' : 'ok',
            'termine_le' => now(),
        ])->save();

        $this->comptabiliserLePlafond($regle, $bride);

        return $passage;
    }

    /** @param array<string, mixed> $demande */
    protected function poser(
        AutomationRule $regle,
        AutomationRun $passage,
        Model $entite,
        array $demande,
        bool $observation,
    ): void {
        $cle = (string) ($demande['cle'] ?? '');
        $parametres = (array) ($demande['parametres'] ?? []);

        $ligne = [
            'automation_rule_id' => $regle->id,
            'automation_run_id' => $passage->id,
            'entite_type' => $regle->entite,
            'entite_id' => (int) $entite->getKey(),
            'action_cle' => $cle,
            'parametres' => $parametres,
            'mode' => $observation ? 'observation' : 'armee',
            'pose_le' => now(),
        ];

        $action = $this->actions->trouver($cle);

        if ($action === null) {
            LigneDeJournal::create($ligne + [
                'resultat' => LigneDeJournal::RESULTAT_ECHOUEE,
                'message' => "Action inconnue : {$cle}",
            ]);

            return;
        }

        // EN OBSERVATION, ON N'APPELLE PAS L'ACTION. On ecrit ce qu'on AURAIT fait.
        if ($observation) {
            LigneDeJournal::create($ligne + ['resultat' => LigneDeJournal::RESULTAT_SIMULEE]);

            return;
        }

        try {
            $resultat = $action->executer($entite, $parametres);
        } catch (Throwable $e) {
            $resultat = ActionResult::echouee(substr($e->getMessage(), 0, 250));
        }

        LigneDeJournal::create($ligne + [
            'resultat' => $resultat->reussie
                ? LigneDeJournal::RESULTAT_EXECUTEE
                : LigneDeJournal::RESULTAT_ECHOUEE,
            'message' => $resultat->message,
        ]);
    }

    /**
     * @param  Builder<Model>  $requete
     *
     * La politique porte TOUJOURS sur l'entite : « une fois » veut dire une fois par entite.
     */
    protected function exclureLeDejaAgi(Builder $requete, AutomationRule $regle): void
    {
        if ($regle->politique_reprise === 'chaque_passage') {
            return;
        }

        $deja = LigneDeJournal::query()
            ->where('automation_rule_id', $regle->id)
            ->where('entite_type', $regle->entite)
            ->whereNotIn('resultat', [
                LigneDeJournal::RESULTAT_REFUSEE,
                LigneDeJournal::RESULTAT_EXPIREE,
            ])
            ->when(
                $regle->politique_reprise === 'une_fois_par_jour',
                fn (Builder $q) => $q->where('pose_le', '>=', now()->subDay())
            )
            ->select('entite_id');

        $requete->whereNotIn($requete->getModel()->getQualifiedKeyName(), $deja);
    }

    protected function poseesAujourdhui(AutomationRule $regle): int
    {
        return LigneDeJournal::query()
            ->where('automation_rule_id', $regle->id)
            ->where('pose_le', '>=', now()->startOfDay())
            ->count();
    }

    /** Le quota BRIDE. C'est l'emballement — trois plafonds d'affilee — qui suspend. */
    protected function comptabiliserLePlafond(AutomationRule $regle, bool $bride): void
    {
        $consecutifs = $bride ? $regle->plafonds_consecutifs + 1 : 0;

        $regle->forceFill([
            'plafonds_consecutifs' => $consecutifs,
            'dernier_passage_le' => now(),
            'etat' => $consecutifs >= 3 ? AutomationRule::ETAT_SUSPENDUE : $regle->etat,
        ])->save();
    }
}
