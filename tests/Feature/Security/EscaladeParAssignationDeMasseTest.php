<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** S'INSCRIRE NE DOIT PAS PERMETTRE DE CHOISIR SON RANG. */
class EscaladeParAssignationDeMasseTest extends TestCase
{
    use RefreshDatabase;

    /** ATTAQUE (a) — une inscription publique qui réclame le rang d'administrateur de plateforme. */
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

    /** GARDE DE NON-RÉGRESSION — pas une attaque qui réussissait. */
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

    /** Les points d'écriture légitimes passent par `forceFill()` : le rattachement d'une société cliente créée pendant l'inscription doit continuer de fonctionner. */
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
