<?php

namespace Tests\Feature\ClientCompany;

use App\Livewire\ClientCompany\ClientCompanyDashboard;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L'espace societe cliente recopiait le systeme en Tailwind brut, comme celui de la societe
 * prestataire avant lui : en-tete maison, cases maison, etats vides maison, et des titres de
 * section en `h2` que la charte rend en Allura capitales — illisibles.
 */
class LEspaceSocieteClienteSuitLeSystemeTest extends TestCase
{
    use RefreshDatabase;

    private function patronne(): User
    {
        $org = OrganizationAccount::factory()->create();

        $patronne = User::factory()->create([
            'organization_account_id' => $org->id,
            'current_organization_id' => $org->id,
            'role' => User::ROLE_ENTREPRISE,
        ]);

        OrganizationMember::query()->create([
            'organization_account_id' => $org->id,
            'user_id' => $patronne->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $patronne->fresh();
    }

    public function test_la_page_emploie_la_coquille_du_systeme(): void
    {
        Livewire::actingAs($this->patronne())->test(ClientCompanyDashboard::class)
            ->assertOk()
            ->assertSee('brio-hero', escape: false);
    }

    public function test_les_indicateurs_emploient_la_case_du_systeme(): void
    {
        Livewire::actingAs($this->patronne())->test(ClientCompanyDashboard::class)
            ->assertSee('brio-stat', escape: false);
    }

    public function test_les_blocs_emploient_la_carte_du_systeme(): void
    {
        Livewire::actingAs($this->patronne())->test(ClientCompanyDashboard::class)
            ->assertSee('brio-card', escape: false);
    }

    public function test_l_etat_vide_emploie_celui_du_systeme(): void
    {
        Livewire::actingAs($this->patronne())->test(ClientCompanyDashboard::class)
            ->assertSee('brio-empty', escape: false);
    }

    /**
     * `h1`/`h2` prennent Allura dans cette charte. Un titre de section en `h2` capitales
     * sortait en cursive : les trois de cet ecran etaient illisibles.
     */
    public function test_aucun_titre_de_section_n_est_un_h2(): void
    {
        $vue = (string) file_get_contents(
            resource_path('views/livewire/client-company/client-company-dashboard.blade.php')
        );

        // Le titre du graphique est le seul `h2` tolere : `brio-graphique-titre` force la sans.
        $h2 = preg_match_all('/<h2\b[^>]*>/', $vue, $trouves) ? $trouves[0] : [];
        $restants = array_values(array_filter($h2, fn ($t) => ! str_contains($t, 'brio-graphique-titre')));

        $this->assertSame([], $restants, 'Ces titres sortiront en Allura : les ecrire en h3.');
    }

    /**
     * TEMOIN — les donnees de l'ecran sont toujours la. Sans lui, les tests ci-dessus
     * resteraient verts sur une page reduite a une coquille vide.
     */
    public function test_temoin_l_ecran_dit_toujours_ce_qu_il_disait(): void
    {
        $patronne = $this->patronne();

        Livewire::actingAs($patronne)->test(ClientCompanyDashboard::class)
            ->assertSee($patronne->currentOrganization->name)
            ->assertSee('Réservations récentes', escape: false)
            ->assertSee('Mes locaux', escape: false)
            ->assertSee('Missions actives', escape: false)
            ->assertSee('Dépenses mois', escape: false);
    }

    /** La barre du haut porte ce que portent les autres espaces. */
    public function test_la_barre_porte_le_theme_la_langue_et_les_notifications(): void
    {
        $reponse = $this->actingAs($this->patronne())->get(route('client-company.dashboard'));

        $reponse->assertOk()
            ->assertSee('Changer le thème', escape: false)
            ->assertSee(route('locale.switch'), escape: false)
            ->assertSee('data-cloche-compteur', escape: false);
    }

    /**
     * La barre porte `backdrop-blur`, qui fait d'elle le bloc conteneur de tout `fixed`
     * descendant : la bulle d'assistant y atterrissait a y=-25, coupee dans le coin.
     */
    public function test_la_bulle_d_assistant_ne_vit_pas_dans_la_barre(): void
    {
        $html = $this->actingAs($this->patronne())
            ->get(route('client-company.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<nav\b/', (string) $html);

        preg_match('/<nav\b.*?<\/nav>/s', (string) $html, $barre);

        $this->assertNotEmpty($barre, 'La barre est introuvable : le temoin ne mesure plus rien.');
        $this->assertStringNotContainsString('assistant-widget', $barre[0]);
        // TEMOIN — la bulle est bien rendue, ailleurs. Sinon le test ci-dessus passerait
        // au vert en mesurant une page qui ne la monte plus du tout.
        $this->assertStringContainsString('assistant-widget', (string) $html);
    }

    /**
     * Le gabarit n'avait PAS de `@stack('scripts')`, et la vue ne poussait pas
     * `apexcharts.js` : `dessinerRepartition` etait `undefined`, l'anneau ne pouvait pas
     * se dessiner. Mesure du 2026-08-29 dans le navigateur.
     */
    public function test_les_scripts_pousses_atteignent_la_page(): void
    {
        $gabarit = (string) file_get_contents(resource_path('views/layouts/client-company.blade.php'));
        $this->assertStringContainsString("@stack('scripts')", $gabarit);

        // `TestCase::setUp` neutralise Vite : sans ce rappel, `@vite` n'emet rien et
        // l'assertion ci-dessous passerait au vert en mesurant un gabarit muet.
        $this->withVite();

        $this->actingAs($this->patronne())
            ->get(route('client-company.dashboard'))
            ->assertOk()
            ->assertSee('apexcharts', escape: false);
    }
}
