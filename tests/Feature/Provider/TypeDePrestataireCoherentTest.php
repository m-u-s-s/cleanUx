<?php

namespace Tests\Feature\Provider;

use App\Enums\ProviderType;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Onboarding\ProviderOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CE QUE LE DISPATCH ACCEPTE, L'ESPACE DOIT L'ACCEPTER.
 *
 * `ProviderType` porte quatre valeurs pour deux notions : `INDEPENDENT` et `INDIVIDUAL`
 * désignent le prestataire seul, `COMPANY` et `COMPANY_WORKER` celui rattaché à une société.
 *
 * Le moteur de répartition le savait — `CandidateFinder` acceptait les deux valeurs de chaque
 * camp — mais le modèle comparait à la seule valeur canonique. Un prestataire inscrit par
 * `ProviderOnboardingService` recevait `individual` : il était CANDIDAT AUX MISSIONS et
 * pourtant refusé de son propre espace, de sa fiche de disponibilités et des gestes de
 * terrain. Le repli sur la colonne héritée `role` masquait le défaut ; il porte
 * `@deprecated` et doit disparaître.
 */
class TypeDePrestataireCoherentTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string}> */
    public static function valeursIndependantes(): array
    {
        return ['independent' => ['independent'], 'individual' => ['individual']];
    }

    /** @return array<string, array{0: string}> */
    public static function valeursDeSociete(): array
    {
        return ['company_worker' => ['company_worker'], 'company' => ['company']];
    }

    private function prestataire(string $type): User
    {
        // `role` neutre EXPRÈS : sans cela, le repli hérité rendrait le test vert
        // quelle que soit la valeur du profil — il mesurerait la colonne, pas la règle.
        $user = User::factory()->create(['role' => 'user']);

        ProviderProfile::updateOrCreate(
            ['user_id' => $user->id],
            ['provider_type' => $type, 'status' => 'active'],
        );

        return $user->refresh();
    }

    /**
     * @dataProvider valeursIndependantes
     */
    public function test_les_deux_valeurs_d_independant_donnent_un_prestataire(string $type): void
    {
        $presta = $this->prestataire($type);

        $this->assertTrue($presta->isProviderIndependent(), "« $type » doit désigner un indépendant");
        $this->assertTrue($presta->isEmploye(), "« $type » doit ouvrir l'espace prestataire");
    }

    /**
     * @dataProvider valeursDeSociete
     */
    public function test_les_deux_valeurs_de_societe_donnent_un_prestataire(string $type): void
    {
        $presta = $this->prestataire($type);

        $this->assertTrue($presta->isProviderCompanyWorker(), "« $type » doit désigner un rattaché");
        $this->assertTrue($presta->isEmploye(), "« $type » doit ouvrir l'espace prestataire");
    }

    /** TÉMOIN NÉGATIF — un compte sans profil n'est toujours pas prestataire. */
    public function test_temoin_un_compte_sans_profil_n_est_pas_prestataire(): void
    {
        $simpleClient = User::factory()->create(['role' => 'user']);

        $this->assertFalse($simpleClient->isEmploye(), 'Sans profil, pas de prestataire');
    }

    /** L'inscription écrit la valeur canonique, celle qu'écrit déjà l'API. */
    public function test_l_inscription_ecrit_la_valeur_canonique(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $profil = app(ProviderOnboardingService::class)->startOnboarding($user);

        $this->assertSame(ProviderType::INDEPENDENT, $profil->provider_type);
        $this->assertTrue($user->refresh()->isEmploye(), "Le prestataire qui s'inscrit doit atteindre son espace");
    }

    /** L'INVARIANT — toute valeur servie au dispatch ouvre aussi l'espace. */
    public function test_toute_valeur_candidate_au_dispatch_ouvre_l_espace(): void
    {
        foreach (ProviderType::toutesLesValeurs() as $valeur) {
            $presta = $this->prestataire($valeur);

            $this->assertTrue(
                $presta->isEmploye(),
                "« $valeur » est interrogée par le dispatch : elle doit ouvrir l'espace prestataire"
            );
        }
    }
}
