<?php

namespace App\Services\Automation;

use App\Services\Conditions\EntityDescriptor;
use App\Services\Conditions\RuleTreeEvaluator;
use App\Services\Conditions\RuleTreeTooComplex;
use Throwable;

/** Dit chaque erreur d'un arbre de conditions avant enregistrement — la ou apply() se tait ou leve d'un bloc. */
class ValidateurDArbre
{
    /** Marge de securite sur NOTRE recursion : le verdict de profondeur reste a apply(). */
    private const PROFONDEUR_SURVEILLEE = RuleTreeEvaluator::PROFONDEUR_MAX + 2;

    public function __construct(protected RuleTreeEvaluator $evaluateur) {}

    /**
     * @param  array<string, mixed>  $arbre
     * @return list<string>
     */
    public function valider(array $arbre, EntityDescriptor $entite): array
    {
        // DECISION : un arbre vide est ACCEPTE, comme pour apply(). C'est RuleRunner, pas ce
        // validateur, qui sait si la regle est restreinte par des identifiants (drain d'evenements).
        if ($arbre === []) {
            return [];
        }

        $erreurs = $this->verifierNoeud($arbre, 'racine', $entite, 1);

        if ($erreurs !== []) {
            return $erreurs;
        }

        // En dernier : appliquer reellement l'arbre attrape ce que la forme seule ne dit pas.
        return $this->verifierApplication($arbre, $entite);
    }

    /**
     * @param  array<string, mixed>  $noeud
     * @return list<string>
     */
    protected function verifierNoeud(array $noeud, string $chemin, EntityDescriptor $entite, int $profondeur): array
    {
        // Au-dela, le verdict revient a apply() : eviter une recursion sans fond sur un arbre hostile.
        if ($profondeur > self::PROFONDEUR_SURVEILLEE) {
            return [];
        }

        foreach (['and', 'or'] as $cle) {
            $enfants = $noeud[$cle] ?? null;

            if (is_array($enfants)) {
                return $this->verifierGroupe($enfants, $chemin, $cle, $entite, $profondeur);
            }
        }

        $sousNot = $noeud['not'] ?? null;

        if ($sousNot !== null) {
            return $this->verifierNoeud((array) $sousNot, "{$chemin}.not", $entite, $profondeur + 1);
        }

        return $this->verifierFeuille($noeud, $chemin, $entite);
    }

    /**
     * @param  array<int|string, mixed>  $enfants
     * @return list<string>
     */
    protected function verifierGroupe(array $enfants, string $chemin, string $cle, EntityDescriptor $entite, int $profondeur): array
    {
        if ($enfants === []) {
            return ["{$chemin}.{$cle} : '{$cle}' ne peut pas etre vide."];
        }

        $erreurs = [];

        foreach (array_values($enfants) as $i => $sous) {
            if (! is_array($sous)) {
                $erreurs[] = "{$chemin}.{$cle}[{$i}] : noeud mal forme.";

                continue;
            }

            $erreurs = array_merge($erreurs, $this->verifierNoeud($sous, "{$chemin}.{$cle}[{$i}]", $entite, $profondeur + 1));
        }

        return $erreurs;
    }

    /**
     * @param  array<string, mixed>  $feuille
     * @return list<string>
     */
    protected function verifierFeuille(array $feuille, string $chemin, EntityDescriptor $entite): array
    {
        if (! isset($feuille['field'], $feuille['op']) || ! is_string($feuille['field']) || ! is_string($feuille['op'])) {
            return ["{$chemin} : noeud mal forme, attendu {field, op, value} ou {and|or|not}."];
        }

        $champ = $feuille['field'];
        $op = $feuille['op'];
        $erreurs = [];

        // Meme predicat que RuleTreeEvaluator::appliquerFeuille : un champ non servable retombe
        // sur le meme piege silencieux (1=0) qu'un champ absent du tout.
        $liaison = $entite->fields()[$champ] ?? null;

        if ($liaison === null || ! $liaison->servable) {
            $erreurs[] = "{$chemin} : champ inconnu '{$champ}'.";
        }

        if (! in_array($op, $entite->operators(), true)) {
            $erreurs[] = "{$chemin} : operateur inconnu '{$op}'.";
        }

        return $erreurs;
    }

    /**
     * @param  array<string, mixed>  $arbre
     * @return list<string>
     */
    protected function verifierApplication(array $arbre, EntityDescriptor $entite): array
    {
        try {
            $requete = $entite->baseQuery();
            $this->evaluateur->apply($requete, $arbre, $entite);
            // LIMIT 0 : la base analyse et rejette une colonne inconnue, sans rapporter une ligne.
            $requete->limit(0)->get();
        } catch (RuleTreeTooComplex $e) {
            return [$e->getMessage()];
        } catch (Throwable $e) {
            // Attrape aussi une panne d'infra (table absente, connexion morte) : assume, le
            // message reste lisible et l'administrateur ne peut de toute facon que le signaler.
            return ["L'arbre ne s'applique pas : ".$e->getMessage()];
        }

        return [];
    }
}
