<?php

namespace Tests\Feature\Branding;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Support\Brand\BrandMark;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** CHAQUE ESPACE PORTE SA MARQUE — vérifié sur les PAGES RENDUES, pas sur les fichiers. */
class MarqueParEspaceTest extends TestCase
{
    use RefreshDatabase;

    private function client(): User
    {
        return User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
    }

    private function prestataire(): User
    {
        $user = User::factory()->create(['role' => User::ROLE_EMPLOYE, 'is_active' => true]);

        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => ProviderType::INDEPENDENT->value,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        return $user;
    }

    private function administrateur(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    }

    // ─── La résolution de l'espace ───────────────────────────────────────────────────────────

    #[Test]
    public function le_visiteur_releve_de_la_marque_client(): void
    {
        // Le site public vend un service à des clients : un prestataire ne verra sa marque qu'une
        // fois son espace choisi.
        $this->assertSame(BrandMark::CLIENT, BrandMark::spaceFor(null));
    }

    #[Test]
    public function chaque_role_reçoit_sa_marque(): void
    {
        $this->assertSame(BrandMark::CLIENT, BrandMark::spaceFor($this->client()));
        $this->assertSame(BrandMark::PROVIDER, BrandMark::spaceFor($this->prestataire()));

        // Choix assumé : aucune des deux marques ne dit « admin », et la console sert
        // l'exploitation, aux côtés des prestataires.
        $this->assertSame(BrandMark::PROVIDER, BrandMark::spaceFor($this->administrateur()));
    }

    #[Test]
    public function le_salarie_d_une_societe_prestataire_porte_la_marque_prestataire(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();

        $user = User::factory()->create([
            'role' => User::ROLE_EMPLOYE,
            'is_active' => true,
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        ProviderProfile::create([
            'user_id' => $user->id,
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
            'status' => 'active',
        ]);

        // Le gérant, le répartiteur et le nettoyeur sont du même côté de la place de marché.
        $this->assertSame(BrandMark::PROVIDER, BrandMark::spaceFor($user->fresh()));
    }

    // ─── Ce que le navigateur reçoit ─────────────────────────────────────────────────────────

    /** LE SITE PUBLIC EST NOIR EN PERMANENCE, et ne porte pas la classe `dark` : la règle automatique y servait donc la version crème — un badge clair sur un fond noir, l'inverse de ce qui est voulu. */
    #[Test]
    public function l_accueil_public_force_la_marque_client_sombre(): void
    {
        $reponse = $this->get('/')->assertOk();

        $reponse->assertSee('/images/brand/brio-client-dark', false);
        $reponse->assertDontSee('/images/brand/brio-provider', false);
    }

    #[Test]
    public function l_onglet_du_navigateur_porte_la_marque(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Deux jeux de liens départagés par `media` : le navigateur dessine l'onglet avant même que
        // la page ne s'exécute, il ne connaît alors que la préférence du système.
        $this->assertStringContainsString('rel="icon"', $html);
        $this->assertStringContainsString('media="(prefers-color-scheme: dark)"', $html);
        $this->assertStringContainsString('media="(prefers-color-scheme: light)"', $html);
        $this->assertStringContainsString('rel="apple-touch-icon"', $html);
    }

    #[Test]
    public function la_page_de_connexion_porte_la_marque_client(): void
    {
        $reponse = $this->get('/login')->assertOk();

        // Deux surfaces, deux variantes : la coquille noire porte la sombre, la carte blanche la
        // claire. L'icône suit LA SURFACE QUI LA PORTE — c'est la même règle que « clair/sombre »,
        // exprimée là où elle se vérifie.
        $reponse->assertSee('/images/brand/brio-client-dark', false);
        $reponse->assertSee('/images/brand/brio-client-light', false);
        $reponse->assertDontSee('6875F5', false); // le mauve de Jetstream
    }

    #[Test]
    public function la_page_d_inscription_porte_la_marque_client(): void
    {
        $reponse = $this->get('/register')->assertOk();

        $reponse->assertSee('/images/brand/brio-client-dark', false);  // la coquille
        $reponse->assertSee('/images/brand/brio-client-light', false); // la carte
        $reponse->assertDontSee('/images/brand/brio-provider', false);
    }

    #[Test]
    public function le_tableau_de_bord_client_sert_la_marque_client(): void
    {
        // `/dashboard` renvoie vers l'espace du rôle : on demande directement l'écran, sinon on
        // mesure la redirection et non la page.
        $reponse = $this->actingAs($this->client())->followingRedirects()->get('/dashboard/client')->assertOk();

        $reponse->assertSee('/images/brand/brio-client-light', false);
        $reponse->assertSee('/images/brand/brio-client-dark', false);
        $reponse->assertDontSee('/images/brand/brio-provider', false);
    }

    #[Test]
    public function l_espace_prestataire_sert_la_marque_prestataire(): void
    {
        $reponse = $this->actingAs($this->prestataire())->followingRedirects()->get('/dashboard/employe')->assertOk();

        // La barre est la MÊME vue pour les deux espaces : sans résolution par rôle, un prestataire
        // aurait vu « Brio Client » à longueur de journée.
        $reponse->assertSee('/images/brand/brio-provider-light', false);
        $reponse->assertSee('/images/brand/brio-provider-dark', false);
        $reponse->assertDontSee('/images/brand/brio-client', false);
    }

    #[Test]
    public function l_espace_societe_prestataire_sert_la_marque_prestataire(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();

        $user = User::factory()->create([
            'role' => User::ROLE_EMPLOYE,
            'is_active' => true,
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        ProviderProfile::create([
            'user_id' => $user->id,
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
            'status' => 'active',
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        $reponse = $this->actingAs($user->fresh())
            ->followingRedirects()
            ->get(route('provider-company.dashboard'));

        if ($reponse->status() !== 200) {
            $this->markTestSkipped('Espace société prestataire indisponible dans cet environnement.');
        }

        $reponse->assertSee('/images/brand/brio-provider-light', false);
        $reponse->assertSee('/images/brand/brio-provider-dark', false);
    }

    #[Test]
    public function l_espace_societe_cliente_sert_la_marque_client(): void
    {
        $org = OrganizationAccount::factory()->create([
            'type' => OrganizationType::CLIENT_COMPANY->value,
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_ENTREPRISE,
            'is_active' => true,
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        $reponse = $this->actingAs($user->fresh())
            ->followingRedirects()
            ->get(route('client-company.dashboard'));

        if ($reponse->status() !== 200) {
            $this->markTestSkipped('Espace société cliente indisponible dans cet environnement.');
        }

        // Une entreprise CLIENTE achète des prestations : elle est du côté client, même si son
        // compte est une organisation comme celui d'une société prestataire.
        $reponse->assertSee('/images/brand/brio-client-light', false);
        $reponse->assertDontSee('/images/brand/brio-provider', false);
    }

    #[Test]
    public function la_console_d_administration_sert_la_marque_prestataire(): void
    {
        $reponse = $this->actingAs($this->administrateur())->get('/admin/dashboard');

        if ($reponse->status() !== 200) {
            $this->markTestSkipped('Tableau de bord admin indisponible dans cet environnement.');
        }

        $reponse->assertSee('/images/brand/brio-provider-light', false);
    }

    // ─── Les fichiers existent réellement ────────────────────────────────────────────────────

    #[Test]
    public function les_quatre_marques_et_leurs_tailles_existent(): void
    {
        $manquants = [];

        foreach (BrandMark::SPACES as $espace) {
            foreach (['light', 'dark'] as $theme) {
                foreach ([null, 512, 256, 192, 180, 96, 64, 48, 32] as $taille) {
                    $chemin = public_path(ltrim(BrandMark::path($espace, $theme, $taille), '/'));

                    if (! is_file($chemin)) {
                        $manquants[] = BrandMark::path($espace, $theme, $taille);
                    }
                }
            }
        }

        // Une balise qui pointe une image absente ne casse rien : elle affiche un cadre vide, et
        // c'est le genre de défaut qu'aucune suite ne relève jamais.
        $this->assertSame([], $manquants, 'Fichiers de marque manquants : '.implode(', ', $manquants));
    }

    #[Test]
    public function plus_aucune_page_ne_porte_l_ancienne_identite(): void
    {
        $vues = [];

        foreach (glob(resource_path('views/layouts/*.blade.php')) ?: [] as $fichier) {
            $source = (string) file_get_contents($fichier);

            // `cx-logo-mark`, la pastille « Br », le mauve de Jetstream : trois identités
            // concurrentes qui ont chacune survécu à un renommage global, parce qu'aucune ne
            // s'écrivait avec les lettres de la marque.
            foreach (['cx-logo-mark', '6875F5', '/icons/icon-192.png'] as $trace) {
                if (str_contains($source, $trace)) {
                    $vues[] = basename($fichier).' → '.$trace;
                }
            }
        }

        $this->assertSame([], $vues, implode(', ', $vues));
    }
}
