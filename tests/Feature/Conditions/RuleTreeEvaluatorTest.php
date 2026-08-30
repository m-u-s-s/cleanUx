<?php

namespace Tests\Feature\Conditions;

use App\Models\User;
use App\Services\Conditions\EntityDescriptor;
use App\Services\Conditions\FieldBinding;
use App\Services\Conditions\RuleTreeEvaluator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuleTreeEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private function entite(): EntityDescriptor
    {
        return new class implements EntityDescriptor
        {
            public function baseQuery(): Builder
            {
                return User::query();
            }

            public function fields(): array
            {
                return [
                    'role' => FieldBinding::colonne('users.role'),
                    'locale' => FieldBinding::colonne('users.locale'),
                    'absent' => FieldBinding::indisponible(),
                ];
            }

            public function operators(): array
            {
                return ['eq', 'neq', 'in', 'is_null'];
            }
        };
    }

    private function compter(array $noeud): int
    {
        $entite = $this->entite();
        $requete = $entite->baseQuery();
        app(RuleTreeEvaluator::class)->apply($requete, $noeud, $entite);

        return $requete->count();
    }

    public function test_une_feuille_filtre_sur_sa_colonne(): void
    {
        User::factory()->create(['role' => 'client']);
        User::factory()->create(['role' => 'admin']);

        $this->assertSame(1, $this->compter(['field' => 'role', 'op' => 'eq', 'value' => 'client']));
    }

    public function test_le_noeud_and_cumule_les_contraintes(): void
    {
        User::factory()->create(['role' => 'client', 'locale' => 'fr']);
        User::factory()->create(['role' => 'client', 'locale' => 'nl']);

        $this->assertSame(1, $this->compter(['and' => [
            ['field' => 'role', 'op' => 'eq', 'value' => 'client'],
            ['field' => 'locale', 'op' => 'eq', 'value' => 'fr'],
        ]]));
    }

    public function test_le_noeud_or_reunit_les_contraintes(): void
    {
        User::factory()->create(['role' => 'client']);
        User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'employe']);

        $this->assertSame(2, $this->compter(['or' => [
            ['field' => 'role', 'op' => 'eq', 'value' => 'client'],
            ['field' => 'role', 'op' => 'eq', 'value' => 'admin'],
        ]]));
    }

    public function test_le_noeud_not_exclut(): void
    {
        User::factory()->create(['role' => 'client']);
        User::factory()->create(['role' => 'admin']);

        $this->assertSame(1, $this->compter([
            'not' => ['field' => 'role', 'op' => 'eq', 'value' => 'client'],
        ]));
    }

    public function test_un_champ_inconnu_ne_correspond_a_personne(): void
    {
        User::factory()->count(3)->create();

        $this->assertSame(0, $this->compter(['field' => 'inexistant', 'op' => 'eq', 'value' => 'x']));
    }

    public function test_un_operateur_hors_liste_ne_correspond_a_personne(): void
    {
        User::factory()->count(3)->create(['role' => 'client']);

        $this->assertSame(0, $this->compter(['field' => 'role', 'op' => 'contains', 'value' => 'cli']));
    }

    public function test_un_champ_indisponible_ne_correspond_a_personne(): void
    {
        User::factory()->count(3)->create();

        $this->assertSame(0, $this->compter(['field' => 'absent', 'op' => 'eq', 'value' => 'x']));
    }

    /** TEMOIN — sans filtre, tout le monde repond. Sans lui, les trois refus ci-dessus
     *  passeraient au vert sur une table vide. */
    public function test_temoin_un_arbre_vide_ne_filtre_rien(): void
    {
        User::factory()->count(3)->create();

        $this->assertSame(3, $this->compter([]));
    }
}
