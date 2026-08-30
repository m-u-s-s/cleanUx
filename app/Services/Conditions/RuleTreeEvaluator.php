<?php

namespace App\Services\Conditions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Le parcours d'un arbre de conditions et sa traduction en Eloquent. Ne connait aucune entite. */
class RuleTreeEvaluator
{
    /**
     * @param  Builder<Model>  $racine
     * @param  array<string, mixed>  $noeud
     */
    public function apply(Builder $racine, array $noeud, EntityDescriptor $entite): void
    {
        if ($noeud === []) {
            return;
        }

        $racine->where(function (Builder $groupe) use ($racine, $noeud, $entite) {
            $this->appliquerNoeud($groupe, $racine, $noeud, $entite);
        });
    }

    /**
     * `$racine` voyage a cote de `$groupe` : une jointure posee sur un constructeur
     * imbrique n'est jamais compilee.
     *
     * @param  Builder<Model>  $groupe
     * @param  Builder<Model>  $racine
     * @param  array<string, mixed>  $noeud
     */
    protected function appliquerNoeud(Builder $groupe, Builder $racine, array $noeud, EntityDescriptor $entite): void
    {
        if (isset($noeud['and']) && is_array($noeud['and'])) {
            $groupe->where(function (Builder $interne) use ($racine, $noeud, $entite) {
                foreach ($noeud['and'] as $sous) {
                    $interne->where(function (Builder $w) use ($racine, $sous, $entite) {
                        $this->appliquerNoeud($w, $racine, $sous, $entite);
                    });
                }
            });

            return;
        }

        if (isset($noeud['or']) && is_array($noeud['or'])) {
            $groupe->where(function (Builder $interne) use ($racine, $noeud, $entite) {
                foreach ($noeud['or'] as $sous) {
                    $interne->orWhere(function (Builder $w) use ($racine, $sous, $entite) {
                        $this->appliquerNoeud($w, $racine, $sous, $entite);
                    });
                }
            });

            return;
        }

        if (isset($noeud['not'])) {
            $groupe->whereNot(function (Builder $interne) use ($racine, $noeud, $entite) {
                $this->appliquerNoeud($interne, $racine, (array) $noeud['not'], $entite);
            });

            return;
        }

        $this->appliquerFeuille($groupe, $racine, $noeud, $entite);
    }

    /**
     * @param  Builder<Model>  $groupe
     * @param  Builder<Model>  $racine
     * @param  array<string, mixed>  $feuille
     */
    protected function appliquerFeuille(Builder $groupe, Builder $racine, array $feuille, EntityDescriptor $entite): void
    {
        $champ = (string) ($feuille['field'] ?? '');
        $op = (string) ($feuille['op'] ?? '');
        $valeur = $feuille['value'] ?? null;

        $liaison = $entite->fields()[$champ] ?? null;

        if ($liaison === null || ! $liaison->servable || ! in_array($op, $entite->operators(), true)) {
            $groupe->whereRaw('1=0');

            return;
        }

        $colonne = $liaison->colonne ?? ($liaison->jointure)($racine);

        if ($colonne === null) {
            $groupe->whereRaw('1=0');

            return;
        }

        $this->appliquerOperateur($groupe, $colonne, $op, $valeur);
    }

    /** @param  Builder<Model>  $q */
    protected function appliquerOperateur(Builder $q, string $colonne, string $op, mixed $valeur): void
    {
        match ($op) {
            'eq' => $q->where($colonne, '=', $valeur),
            'neq' => $q->where($colonne, '!=', $valeur),
            'in' => $q->whereIn($colonne, (array) $valeur),
            'not_in' => $q->whereNotIn($colonne, (array) $valeur),
            'gt' => $q->where($colonne, '>', $valeur),
            'gte' => $q->where($colonne, '>=', $valeur),
            'lt' => $q->where($colonne, '<', $valeur),
            'lte' => $q->where($colonne, '<=', $valeur),
            'older_than_days' => $q->where($colonne, '<=', now()->subDays((int) $valeur)),
            'newer_than_days' => $q->where($colonne, '>=', now()->subDays((int) $valeur)),
            'is_null' => $q->whereNull($colonne),
            'is_not_null' => $q->whereNotNull($colonne),
            default => $q->whereRaw('1=0'),
        };
    }
}
