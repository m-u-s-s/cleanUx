<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * S'INSCRIRE NE DOIT PAS PERMETTRE DE CHOISIR SON RANG.
 *
 * Fortify appelle `CreateNewUser::create()` avec `$request->all()` — c'est le framework qui le fait,
 * pas nous, et cela ne se change pas depuis l'application. Tant que `platform_role`, `role`,
 * `organization_account_id` et `current_organization_id` figuraient dans `User::$fillable`, le
 * formulaire public d'inscription ouvrait quatre colonnes de décision :
 *
 *   - `platform_role=admin`         → toute la console d'administration (`canAccessAdminModule()`) ;
 *   - `role=admin` / `role=dispatcher` → les gardes `CreateNewUser` lisaient même `$input['role']`
 *                                     explicitement, et la callback de diffusion
 *                                     `providers.presence` autorisait sur cette valeur ;
 *   - `organization_account_id` / `current_organization_id` → l'organisation dont on lit les
 *                                     données, c'est-à-dire les locaux, contrats et factures
 *                                     d'une société qui n'est pas la sienne.
 *
 * DEUX NIVEAUX DE PREUVE, ET ILS NE SE VALENT PAS. `platform_role` et `role` étaient ATTEIGNABLES :
 * `CreateNewUser` les lisait explicitement dans `$input`, et le test d'inscription ci-dessous
 * échouait avant le correctif. Les deux colonnes d'organisation n'avaient, elles, aucun appelant
 * connu qui les exposait : elles étaient assignables sans être offertes. Le test par `fill()` est
 * ce qui prouve leur fermeture — on ne prétend pas avoir refermé une porte qui était close.
 */
class EscaladeParAssignationDeMasseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ATTAQUE (a) — une inscription publique qui réclame le rang d'administrateur de plateforme.
     *
     * Sans le correctif, `CreateNewUser` lisait `$input['platform_role'] ?? User::PLATFORM_USER` :
     * la valeur postée gagnait, et `assertFalse($compte->isPlatformAdmin())` tombait. C'est
     * l'assertion qui porte la faille.
     */
    public function test_une_inscription_ne_peut_pas_se_declarer_administrateur_de_plateforme(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped("L'inscription est désactivée sur cet environnement.");
        }

        $this->post('/register', [
            'name' => 'Attaquant Poli',
            'email' => 'attaquant@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),

            // Les deux champs cachés qui n'existent dans aucune règle de validation, et qui
            // arrivaient malgré tout jusqu'à la colonne.
            'platform_role' => 'super_admin',
            'role' => 'admin',
        ]);

        $compte = User::query()->where('email', 'attaquant@example.test')->firstOrFail();

        $this->assertFalse(
            $compte->isPlatformAdmin(),
            "L'inscription publique a produit un administrateur de plateforme.",
        );
        $this->assertFalse($compte->isSuperAdmin());
        $this->assertSame(User::PLATFORM_USER, $compte->platform_role);

        // `role` reste déduit du type de compte demandé — ici un client particulier par défaut.
        $this->assertSame('client', $compte->role);
        $this->assertFalse($compte->canAccessAdminModule());
    }

    /**
     * GARDE DE NON-RÉGRESSION — pas une attaque qui réussissait.
     *
     * Soyons exacts : `CreateNewUser` construit un tableau littéral, il n'a jamais lu
     * `$input['organization_account_id']`. Ces deux colonnes étaient assignables en masse sans
     * qu'un appelant connu ne les expose — le trou était en profondeur, pas ouvert. Ce test tombe
     * donc AVANT comme APRÈS le correctif ; c'est la preuve par `fill()` un peu plus bas qui porte
     * le changement de `$fillable`.
     *
     * Il reste utile : le jour où quelqu'un élargit ce tableau littéral ou branche un
     * `fill($donneesValidees)` sur `User`, cette assertion est la première à parler.
     */
    public function test_une_inscription_ne_peut_pas_se_rattacher_a_une_organisation_choisie(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped("L'inscription est désactivée sur cet environnement.");
        }

        $this->post('/register', [
            'name' => 'Voisin Curieux',
            'email' => 'voisin@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),

            'organization_account_id' => 4242,
            'current_organization_id' => 4242,
        ]);

        $compte = User::query()->where('email', 'voisin@example.test')->firstOrFail();

        $this->assertNull($compte->organization_account_id);
        $this->assertNull($compte->current_organization_id);
    }

    /**
     * Les quatre colonnes de décision, une par une.
     *
     * `Model::preventSilentlyDiscardingAttributes` étant actif hors production, la tentative LÈVE
     * au lieu d'être ignorée : la garantie est plus forte qu'un attribut resté nul, et une
     * régression qui les remettrait dans `$fillable` fait tomber ce test immédiatement.
     *
     * @return array<int, array{0: string, 1: mixed}>
     */
    public static function colonnesDeDecision(): array
    {
        return [
            ['platform_role', 'super_admin'],
            ['role', 'admin'],
            ['organization_account_id', 4242],
            ['current_organization_id', 4242],
        ];
    }

    #[DataProvider('colonnesDeDecision')]
    public function test_les_colonnes_de_decision_ne_sont_pas_assignables_en_masse(string $colonne, mixed $valeur): void
    {
        // Le témoin : une colonne de préférence reste bien assignable, donc l'échec ci-dessous
        // vient de la colonne testée et non d'un `$fillable` vidé par erreur.
        $this->assertSame('Témoin', (new User)->fill(['name' => 'Témoin'])->name);

        $this->expectException(MassAssignmentException::class);

        (new User)->fill([$colonne => $valeur]);
    }

    /**
     * Les points d'écriture légitimes passent par `forceFill()` : le rattachement d'une société
     * cliente créée pendant l'inscription doit continuer de fonctionner.
     */
    public function test_l_inscription_d_une_societe_cliente_rattache_toujours_son_signataire(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped("L'inscription est désactivée sur cet environnement.");
        }

        $this->post('/register', [
            'name' => 'Patronne Légitime',
            'email' => 'patronne@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'account_type' => 'client_company',
            'company_name' => 'Nettoyage Général SA',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $compte = User::query()->where('email', 'patronne@example.test')->firstOrFail();

        $this->assertNotNull($compte->organization_account_id, "Le signataire n'a pas été rattaché à sa société.");
        $this->assertSame($compte->organization_account_id, $compte->current_organization_id);
    }
}
