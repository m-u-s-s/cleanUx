<?php

namespace Tests\Feature\Conditions;

use App\Models\User;
use App\Services\Conditions\EntityDescriptor;
use App\Services\Conditions\FieldBinding;
use App\Services\Conditions\RuleTreeEvaluator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'ECHAPPEMENT DES JOKERS SQL, SUR LES DEUX MOTEURS.
 *
 * L'antislash ne peut pas servir : `ESCAPE '\'` est une erreur 1064 sur MySQL, et
 * `ESCAPE '\\'` un refus sur SQLite. `!` passe des deux cotes.
 */
class LikeEscapingTest extends TestCase
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
                return ['name' => FieldBinding::colonne('users.name')];
            }

            public function operators(): array
            {
                return ['contains', 'starts_with', 'ends_with'];
            }
        };
    }

    private function noms(string $op, string $valeur): array
    {
        $entite = $this->entite();
        $requete = $entite->baseQuery();
        app(RuleTreeEvaluator::class)->apply($requete, ['field' => 'name', 'op' => $op, 'value' => $valeur], $entite);

        return $requete->pluck('name')->sort()->values()->all();
    }

    public function test_le_pourcent_est_un_caractere_et_non_un_joker(): void
    {
        User::factory()->create(['name' => 'a%b']);
        User::factory()->create(['name' => 'axb']);   // TEMOIN : ne doit PAS remonter

        $this->assertSame(['a%b'], $this->noms('contains', 'a%b'));
    }

    public function test_le_souligne_est_un_caractere_et_non_un_joker(): void
    {
        User::factory()->create(['name' => 'a_b']);
        User::factory()->create(['name' => 'axb']);   // TEMOIN

        $this->assertSame(['a_b'], $this->noms('contains', 'a_b'));
    }

    public function test_le_caractere_d_echappement_lui_meme_reste_litteral(): void
    {
        User::factory()->create(['name' => 'a!b']);
        User::factory()->create(['name' => 'axb']);   // TEMOIN

        $this->assertSame(['a!b'], $this->noms('contains', 'a!b'));
    }

    public function test_starts_with_et_ends_with_ancrent_bien(): void
    {
        User::factory()->create(['name' => 'alpha']);
        User::factory()->create(['name' => 'beta-alpha']);

        $this->assertSame(['alpha'], $this->noms('starts_with', 'alp'));
        $this->assertSame(['alpha', 'beta-alpha'], $this->noms('ends_with', 'pha'));
    }

    /** TEMOIN — `contains` trouve bien quelque chose quand rien n'est echappe. Sans lui, les
     *  trois tests ci-dessus passeraient au vert sur un LIKE qui ne rend jamais rien. */
    public function test_temoin_contains_trouve_une_correspondance_ordinaire(): void
    {
        User::factory()->create(['name' => 'Marc Dubois']);

        $this->assertSame(['Marc Dubois'], $this->noms('contains', 'Dubo'));
    }
}
