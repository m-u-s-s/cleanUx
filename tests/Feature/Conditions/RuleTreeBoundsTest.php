<?php

namespace Tests\Feature\Conditions;

use App\Models\User;
use App\Services\Conditions\EntityDescriptor;
use App\Services\Conditions\FieldBinding;
use App\Services\Conditions\RuleTreeEvaluator;
use App\Services\Conditions\RuleTreeTooComplex;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuleTreeBoundsTest extends TestCase
{
    use RefreshDatabase;

    private function entite(): EntityDescriptor
    {
        return new class implements EntityDescriptor
        {
            public function libelle(): string
            {
                return 'Double de test';
            }

            public function baseQuery(): Builder
            {
                return User::query();
            }

            public function fields(): array
            {
                return ['role' => FieldBinding::colonne('users.role')];
            }

            public function operators(): array
            {
                return ['eq'];
            }
        };
    }

    private function feuille(): array
    {
        return ['field' => 'role', 'op' => 'eq', 'value' => 'client'];
    }

    /** Un arbre de profondeur $n : n-1 groupes `and` imbriques, puis une feuille. */
    private function profondeur(int $n): array
    {
        $noeud = $this->feuille();

        for ($i = 1; $i < $n; $i++) {
            $noeud = ['and' => [$noeud]];
        }

        return $noeud;
    }

    private function evaluer(array $noeud): void
    {
        $entite = $this->entite();
        $requete = $entite->baseQuery();
        app(RuleTreeEvaluator::class)->apply($requete, $noeud, $entite);
    }

    public function test_un_arbre_a_la_profondeur_maximale_passe(): void
    {
        $this->evaluer($this->profondeur(RuleTreeEvaluator::PROFONDEUR_MAX));

        $this->assertTrue(true, 'Un arbre a la limite exacte doit passer.');
    }

    public function test_un_arbre_trop_profond_est_refuse(): void
    {
        $this->expectException(RuleTreeTooComplex::class);

        $this->evaluer($this->profondeur(RuleTreeEvaluator::PROFONDEUR_MAX + 1));
    }

    public function test_un_arbre_au_nombre_de_noeuds_maximal_passe(): void
    {
        // 1 groupe + (NOEUDS_MAX - 1) feuilles.
        $feuilles = array_fill(0, RuleTreeEvaluator::NOEUDS_MAX - 1, $this->feuille());

        $this->evaluer(['and' => $feuilles]);

        $this->assertTrue(true, 'Un arbre au nombre de noeuds exact doit passer.');
    }

    public function test_un_arbre_trop_large_est_refuse(): void
    {
        $this->expectException(RuleTreeTooComplex::class);

        $feuilles = array_fill(0, RuleTreeEvaluator::NOEUDS_MAX, $this->feuille());

        $this->evaluer(['and' => $feuilles]);
    }
}
