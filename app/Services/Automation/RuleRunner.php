<?php

namespace App\Services\Automation;

use App\Models\AutomationAction as LigneDeJournal;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Services\Automation\Registre\ActionRegistre;
use App\Services\Automation\Registre\EntiteRegistre;
use App\Services\Conditions\EntityDescriptor;
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
        protected EtatDeRegle $etats,
        protected ReglagesDActions $reglages,
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

        // UNE REGLE N'AGIT JAMAIS SANS AVOIR OBSERVE. C'est la contrainte fondatrice, et
        // c'est ici qu'elle produit ses effets : tous les chemins passent par ce point.
        if (! $observation && ! $this->etats->aDejaObserve($regle)) {
            return $this->echecSansBalayage($regle, $passage, $observation, "Règle armée sans journal d'observation.");
        }

        $entite = $this->entites->descripteur($regle->entite);

        if ($entite === null) {
            return $this->echecSansBalayage($regle, $passage, $observation, "Entité inconnue : {$regle->entite}");
        }

        // Sans identifiants, une regle sans condition balaierait toute la table. Avec eux
        // (le drain d'evenements), ce sont LES IDENTIFIANTS qui restreignent : legitime.
        if ($identifiants === null && ($regle->conditions ?? []) === []) {
            return $this->echecSansBalayage(
                $regle,
                $passage,
                $observation,
                'Aucune condition : la règle balaierait toute la table.'
            );
        }

        // TOUT ce qui suit peut lever (arbre trop complexe, requete malformee...) : capture
        // au MEME passage deja cree, jamais un second — voir enregistrerEchec() plus bas.
        try {
            return $this->balayer($regle, $passage, $entite, $identifiants, $observation);
        } catch (Throwable $e) {
            return $this->echecSansBalayage($regle, $passage, $observation, mb_substr($e->getMessage(), 0, 250));
        }
    }

    /** @param  list<int>|null  $identifiants */
    protected function balayer(
        AutomationRule $regle,
        AutomationRun $passage,
        EntityDescriptor $entite,
        ?array $identifiants,
        bool $observation,
    ): AutomationRun {
        $requete = $entite->baseQuery();
        $this->evaluateur->apply($requete, $regle->conditions ?? [], $entite);

        if ($identifiants !== null) {
            $requete->whereKey($identifiants);
        }

        $this->exclureLeDejaAgi($requete, $regle, $observation);

        // Le plafond borne des LIGNES ; le quota borne des ENTITES. Une regle a N actions
        // depense N lignes par entite : on convertit avant de comparer.
        $parEntite = max(1, count($regle->actions ?? []));
        $restantAujourdhui = max(0, $regle->plafond_journalier - $this->poseesAujourdhui($regle, $observation));
        $quota = min($regle->quota_par_passage, intdiv($restantAujourdhui, $parEntite));

        // L'ORDRE DU BALAYAGE, EXPLICITE : le meme sert a prendre le quota juste apres,
        // sinon la queue soustraite plus bas ne serait pas celle reellement coupee.
        $cleModele = $requete->getModel()->getQualifiedKeyName();
        $requete->orderBy($cleModele);
        $eligibles = (clone $requete)->pluck($cleModele)->map(fn ($id) => (int) $id)->all();

        $lignes = $requete->limit($quota)->get();
        $bride = count($eligibles) > $quota;
        $posees = 0;
        $echouees = 0;

        // UNE lecture des reglages par passage, sinon c'est une requete par ligne posee. Une
        // bascule d'administrateur est donc prise au passage suivant, jamais en plein balayage.
        $autonomies = $this->reglages->tous();

        foreach ($lignes as $ligne) {
            foreach (($regle->actions ?? []) as $demande) {
                $resultat = $this->poser($regle, $passage, $ligne, (array) $demande, $observation, $autonomies);
                $posees++;

                if ($resultat === LigneDeJournal::RESULTAT_ECHOUEE) {
                    $echouees++;
                }
            }
        }

        // Entierement en echec : au moins une ligne posee, et aucune n'a reussi. Vrai dans
        // les deux modes — l'observation doit montrer ses echecs, pas les maquiller en 'ok'.
        $echecTotal = $posees > 0 && $echouees === $posees;

        $precedent = AutomationRun::query()
            ->where('automation_rule_id', $regle->id)
            ->where('mode', $passage->mode)
            ->where('id', '<', $passage->id)
            ->orderByDesc('id')
            ->value('entites_eligibles');

        // EMBALLEMENT : bride, et la population n'a pas diminue depuis le passage precedent.
        $emballement = $bride && $precedent !== null && count($eligibles) >= $precedent;

        // FINI = ce qu'on m'a demande, MOINS ce que le quota me fait remettre a plus tard.
        // Une entite que les conditions ne retiennent pas, ou qui n'existe plus, est finie.
        $finies = $identifiants !== null
            ? array_values(array_diff($identifiants, array_slice($eligibles, $quota)))
            : [];

        $passage->forceFill([
            'entites_vues' => $lignes->count(),
            'entites_eligibles' => count($eligibles),
            'entites_finies' => $finies,
            'actions_posees' => $posees,
            'statut' => $echecTotal ? 'echec' : ($bride ? 'plafond_atteint' : 'ok'),
            'termine_le' => now(),
        ])->save();

        $this->comptabiliserLePlafond($regle, $observation, $emballement, $echecTotal);

        return $passage;
    }

    /** Une regle qui leve (arbre trop complexe...) avant tout balayage : meme sort qu'un
     *  refus en amont, via le meme chemin — un passage `echec` qui peut suspendre. */
    public function enregistrerEchec(AutomationRule $regle, string $message): AutomationRun
    {
        $observation = $regle->etat === AutomationRule::ETAT_OBSERVATION;

        $passage = AutomationRun::create([
            'automation_rule_id' => $regle->id,
            'mode' => $observation ? 'observation' : 'armee',
            'demarre_le' => now(),
        ]);

        return $this->echecSansBalayage($regle, $passage, $observation, $message);
    }

    /**
     * @param  array<string, mixed>  $demande
     * @param  array<string, bool>  $autonomies  lues UNE fois par passage, jamais par ligne
     * @return string le resultat ecrit — pour que l'appelant compte les echecs sans requete de plus
     */
    protected function poser(
        AutomationRule $regle,
        AutomationRun $passage,
        Model $entite,
        array $demande,
        bool $observation,
        array $autonomies,
    ): string {
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

            return LigneDeJournal::RESULTAT_ECHOUEE;
        }

        // Le contrat declare les entites supportees ; jusqu'ici rien ne le faisait respecter.
        if (! in_array($regle->entite, $action->entitesSupportees(), true)) {
            LigneDeJournal::create($ligne + [
                'resultat' => LigneDeJournal::RESULTAT_ECHOUEE,
                'message' => "L'action « {$cle} » ne supporte pas l'entité « {$regle->entite} ».",
            ]);

            return LigneDeJournal::RESULTAT_ECHOUEE;
        }

        // EN OBSERVATION, ON N'APPELLE PAS L'ACTION. On ecrit ce qu'on AURAIT fait.
        if ($observation) {
            LigneDeJournal::create($ligne + ['resultat' => LigneDeJournal::RESULTAT_SIMULEE]);

            return LigneDeJournal::RESULTAT_SIMULEE;
        }

        // L'AUTONOMIE EST EXPLICITE. Sans elle on PROPOSE : rien n'est appele, et la ligne
        // `proposee` gele l'entite (voir exclureLeDejaAgi) jusqu'a ce qu'un humain tranche.
        if (! ($autonomies[$cle] ?? false)) {
            LigneDeJournal::create($ligne + ['resultat' => LigneDeJournal::RESULTAT_PROPOSEE]);

            return LigneDeJournal::RESULTAT_PROPOSEE;
        }

        try {
            $resultat = $action->executer($entite, $parametres);
        } catch (Throwable $e) {
            $resultat = ActionResult::echouee(mb_substr($e->getMessage(), 0, 250));
        }

        $resultatCle = $resultat->reussie
            ? LigneDeJournal::RESULTAT_EXECUTEE
            : LigneDeJournal::RESULTAT_ECHOUEE;

        LigneDeJournal::create($ligne + [
            'resultat' => $resultatCle,
            'message' => $resultat->message,
        ]);

        return $resultatCle;
    }

    /**
     * @param  Builder<Model>  $requete
     *
     * La politique porte TOUJOURS sur l'entite : « une fois » veut dire une fois par entite.
     */
    protected function exclureLeDejaAgi(Builder $requete, AutomationRule $regle, bool $observation): void
    {
        $cleModele = $requete->getModel()->getQualifiedKeyName();

        // UNE PROPOSITION PENDANTE GELE SON ENTITE, quelle que soit la politique : reproposer
        // ne fait agir personne, ca noie la file de doublons. Seul un humain (ou l'expiration) tranche.
        $requete->whereNotIn($cleModele, $this->propositionsPendantesQuery($regle, $observation));

        if ($regle->politique_reprise === 'chaque_passage') {
            return;
        }

        $requete->whereNotIn($cleModele, $this->dejaAgiQuery($regle, $observation));
    }

    /**
     * Les entites qui attendent une decision humaine. Ni fenetre de temps ni politique : une
     * proposition ne se libere que par un verdict ou par l'expiration.
     *
     * @return Builder<LigneDeJournal>
     */
    protected function propositionsPendantesQuery(AutomationRule $regle, bool $observation): Builder
    {
        return LigneDeJournal::query()
            ->where('automation_rule_id', $regle->id)
            ->where('entite_type', $regle->entite)
            ->where('mode', $observation ? 'observation' : 'armee')
            ->where('resultat', LigneDeJournal::RESULTAT_PROPOSEE)
            ->select('entite_id');
    }

    /**
     * La requete « deja agi » brute, partagee par l'exclusion du balayage.
     *
     * DEUX RESULTATS SEULEMENT LIBERENT INCONDITIONNELLEMENT : `echouee` (rien ne s'est passe,
     * on retente) et `expiree` (personne n'a decide, on redemande). `refusee` est une DECISION
     * humaine : elle se soumet a `politique_reprise` comme `executee` et `validee`.
     *
     * @return Builder<LigneDeJournal>
     */
    protected function dejaAgiQuery(AutomationRule $regle, bool $observation): Builder
    {
        return LigneDeJournal::query()
            ->where('automation_rule_id', $regle->id)
            ->where('entite_type', $regle->entite)
            ->where('mode', $observation ? 'observation' : 'armee')
            ->whereNotIn('resultat', [
                LigneDeJournal::RESULTAT_EXPIREE,
                LigneDeJournal::RESULTAT_ECHOUEE,
            ])
            ->when(
                $regle->politique_reprise === 'une_fois_par_jour',
                fn (Builder $q) => $q->where('pose_le', '>=', now()->subDay())
            )
            ->select('entite_id');
    }

    protected function poseesAujourdhui(AutomationRule $regle, bool $observation): int
    {
        return LigneDeJournal::query()
            ->where('automation_rule_id', $regle->id)
            ->where('mode', $observation ? 'observation' : 'armee')
            ->where('pose_le', '>=', now()->startOfDay())
            ->count();
    }

    /**
     * Un retour anticipe qui n'a rien balaye : pas de population a mesurer, mais un
     * passage qui compte quand meme pour la cadence et, en mode arme, pour les echecs.
     */
    protected function echecSansBalayage(
        AutomationRule $regle,
        AutomationRun $passage,
        bool $observation,
        string $message,
    ): AutomationRun {
        $passage->forceFill([
            'statut' => 'echec',
            'message' => $message,
            'entites_eligibles' => null,
            'termine_le' => now(),
        ])->save();

        // Motif propre a CE retour anticipe : sinon l'admin lit « entierement en echec »
        // pour une regle qui n'a en realite jamais pu balayer quoi que ce soit.
        $this->comptabiliserLePlafond(
            $regle,
            $observation,
            emballement: false,
            echecTotal: true,
            motifEchec: "Trois passages consécutifs en échec : {$message}"
        );

        return $passage;
    }

    /**
     * Le quota BRIDE, l'emballement et les echecs suspendent — mais seulement en mode arme :
     * observer une grosse population, ou une action qui n'existe pas encore, ne doit rien punir.
     */
    protected function comptabiliserLePlafond(
        AutomationRule $regle,
        bool $observation,
        bool $emballement,
        bool $echecTotal,
        ?string $motifEchec = null,
    ): void {
        if ($observation) {
            $regle->forceFill(['dernier_passage_le' => now()])->save();

            return;
        }

        $plafonds = $emballement ? $regle->plafonds_consecutifs + 1 : 0;
        $echecs = $echecTotal ? $regle->echecs_consecutifs + 1 : 0;

        $regle->forceFill([
            'plafonds_consecutifs' => $plafonds,
            'echecs_consecutifs' => $echecs,
            'dernier_passage_le' => now(),
        ])->save();

        // La decision de suspendre passe PAR EtatDeRegle : elle journalise, elle ne se
        // contente plus d'ecrire `etat` en silence.
        if ($plafonds >= 3) {
            $this->etats->suspendre($regle->fresh(), 'Trois plafonds consécutifs : la population visée ne diminue pas.');
        } elseif ($echecs >= 3) {
            $this->etats->suspendre($regle->fresh(), $motifEchec ?? 'Trois passages consécutifs entièrement en échec.');
        }
    }
}
