<?php

namespace Tests\Feature\Automation;

use App\Models\User;
use App\Services\Automation\ValidateurDArbre;
use App\Services\Conditions\EntityDescriptor;
use App\Services\Conditions\FieldBinding;
use App\Services\Conditions\RuleTreeEvaluator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ValidateurDArbreTest extends TestCase
{
    use RefreshDatabase;

    private function validateur(): ValidateurDArbre
    {
        return app(ValidateurDArbre::class);
    }

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

    /** Une jointure qui leve : seul moyen d'exercer le catch générique de verifierApplication(). */
    private function entiteAvecJointureCassee(): EntityDescriptor
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
                return [
                    'role' => FieldBinding::jointe(function (Builder $racine): ?string {
                        throw new RuntimeException('jointure cassee');
                    }),
                ];
            }

            public function operators(): array
            {
                return ['eq'];
            }
        };
    }

    /** Un seul champ 'x', dont la colonne est parametrable — pour isoler l'effet de la colonne seule. */
    private function entiteColonneUnique(string $colonne): EntityDescriptor
    {
        return new class($colonne) implements EntityDescriptor
        {
            public function __construct(private string $colonne) {}

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
                return ['x' => FieldBinding::colonne($this->colonne)];
            }

            public function operators(): array
            {
                return ['eq'];
            }
        };
    }

    /** @return array<string, mixed> */
    private function feuille(string $champ = 'role', string $op = 'eq', mixed $valeur = 'client'): array
    {
        return ['field' => $champ, 'op' => $op, 'value' => $valeur];
    }

    /**
     * DÉCISION : un arbre vide est ACCEPTÉ par ce validateur.
     * apply() le traite comme une forme valide (aucun filtre) ; c'est RuleRunner, pas ce
     * validateur, qui sait si la règle est restreinte par des identifiants (drain d'événements)
     * ou balaierait toute la table — refuser ici dupliquerait une décision qui n'est pas la
     * sienne, avec moins de contexte que RuleRunner.
     */
    public function test_un_arbre_vide_ne_produit_aucune_erreur(): void
    {
        $this->assertSame([], $this->validateur()->valider([], $this->entite()));
    }

    /** TÉMOIN — sans lui, tous les refus ci-dessous pourraient passer au vert par accident. */
    public function test_un_arbre_valide_ne_produit_aucune_erreur(): void
    {
        $arbre = ['and' => [
            $this->feuille('role', 'eq', 'client'),
            $this->feuille('locale', 'eq', 'fr'),
        ]];

        $this->assertSame([], $this->validateur()->valider($arbre, $this->entite()));
    }

    public function test_un_champ_inconnu_est_signale(): void
    {
        $erreurs = $this->validateur()->valider($this->feuille('inexistant'), $this->entite());

        $this->assertSame(["racine : champ inconnu 'inexistant'."], $erreurs);
    }

    /** Un champ DÉCLARÉ mais non servable est le même piège que le champ inconnu : silence à l'exécution. */
    public function test_un_champ_indisponible_est_signale(): void
    {
        $erreurs = $this->validateur()->valider($this->feuille('absent'), $this->entite());

        $this->assertSame(["racine : champ inconnu 'absent'."], $erreurs);
    }

    public function test_un_operateur_inconnu_est_signale(): void
    {
        $erreurs = $this->validateur()->valider($this->feuille('role', 'contains'), $this->entite());

        $this->assertSame(["racine : operateur inconnu 'contains'."], $erreurs);
    }

    public function test_un_noeud_mal_forme_est_signale(): void
    {
        $erreurs = $this->validateur()->valider(['champ' => 'role', 'operateur' => 'eq'], $this->entite());

        $this->assertSame(
            ['racine : noeud mal forme, attendu {field, op, value} ou {and|or|not}.'],
            $erreurs
        );
    }

    public function test_un_groupe_and_vide_est_signale(): void
    {
        $erreurs = $this->validateur()->valider(['and' => []], $this->entite());

        $this->assertSame(["racine.and : 'and' ne peut pas etre vide."], $erreurs);
    }

    public function test_un_arbre_trop_profond_est_signale(): void
    {
        $noeud = $this->feuille();

        for ($i = 1; $i < RuleTreeEvaluator::PROFONDEUR_MAX + 1; $i++) {
            $noeud = ['and' => [$noeud]];
        }

        $erreurs = $this->validateur()->valider($noeud, $this->entite());

        $this->assertSame(['Arbre trop profond : '.RuleTreeEvaluator::PROFONDEUR_MAX.' niveaux au plus.'], $erreurs);
    }

    /** TÉMOIN de la borne : la limite EXACTE doit passer, sinon la borne serait décalée d'un cran. */
    public function test_un_arbre_a_la_profondeur_maximale_est_valide(): void
    {
        $noeud = $this->feuille();

        for ($i = 1; $i < RuleTreeEvaluator::PROFONDEUR_MAX; $i++) {
            $noeud = ['and' => [$noeud]];
        }

        $this->assertSame([], $this->validateur()->valider($noeud, $this->entite()));
    }

    public function test_un_arbre_trop_large_est_signale(): void
    {
        $feuilles = array_fill(0, RuleTreeEvaluator::NOEUDS_MAX, $this->feuille());

        $erreurs = $this->validateur()->valider(['and' => $feuilles], $this->entite());

        $this->assertSame(['Arbre trop large : '.RuleTreeEvaluator::NOEUDS_MAX.' noeuds au plus.'], $erreurs);
    }

    /**
     * Le catch générique de verifierApplication() a besoin de son propre témoin : sans lui, une
     * jointure qui lève remonterait telle quelle au lieu de devenir une erreur lisible.
     */
    public function test_une_jointure_qui_leve_devient_une_erreur_lisible(): void
    {
        $erreurs = $this->validateur()->valider($this->feuille(), $this->entiteAvecJointureCassee());

        $this->assertSame(["L'arbre ne s'applique pas : jointure cassee"], $erreurs);
    }

    /**
     * La forme seule ne peut pas voir une colonne absente de la table : seule l'exécution réelle
     * le peut. Sans exécution, ce cas passerait `valider()` sans erreur.
     */
    public function test_une_colonne_inexistante_devient_une_erreur_lisible(): void
    {
        $erreurs = $this->validateur()->valider(
            $this->feuille('x'),
            $this->entiteColonneUnique('users.colonne_absente')
        );

        $this->assertNotSame([], $erreurs);
    }

    /** TÉMOIN — le même descripteur, une colonne RÉELLE : aucune erreur. Isole l'effet de la colonne. */
    public function test_temoin_une_colonne_existante_ne_produit_aucune_erreur(): void
    {
        $erreurs = $this->validateur()->valider(
            $this->feuille('x'),
            $this->entiteColonneUnique('users.role')
        );

        $this->assertSame([], $erreurs);
    }
}
