<?php

namespace Tests\Feature\Conditions;

use App\Services\Conditions\EntityDescriptor;
use App\Services\Conditions\RuleTreeEvaluator;
use App\Services\Marketing\UserSegmentDescriptor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Un champ declare vers une colonne absente ne cassait qu'a l'execution, chez un utilisateur. */
class DescriptorFieldsResolveTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{class-string<EntityDescriptor>}> */
    public static function descripteurs(): array
    {
        return [
            'utilisateurs' => [UserSegmentDescriptor::class],
        ];
    }

    /** @param class-string<EntityDescriptor> $classe */
    #[DataProvider('descripteurs')]
    public function test_chaque_champ_declare_produit_une_requete_qui_s_execute(string $classe): void
    {
        $echecs = [];

        foreach (array_keys(app($classe)->fields()) as $champ) {
            $entite = app($classe);
            $requete = $entite->baseQuery();

            try {
                app(RuleTreeEvaluator::class)->apply(
                    $requete,
                    ['field' => $champ, 'op' => 'is_not_null', 'value' => null],
                    $entite
                );
                $requete->count();
            } catch (\Throwable $e) {
                $echecs[] = $champ.' : '.substr($e->getMessage(), 0, 90);
            }
        }

        $this->assertSame([], $echecs, "Ces champs declares ne s'executent pas :\n".implode("\n", $echecs));
    }

    /** TEMOIN — le descripteur declare bien des champs. Sans lui, le test passerait au vert
     *  sur une liste vide. */
    public function test_temoin_le_descripteur_declare_des_champs(): void
    {
        $this->assertNotEmpty(app(UserSegmentDescriptor::class)->fields());
    }
}
