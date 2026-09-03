<?php

namespace Tests\Feature\PeerRental;

use App\Models\PeerStay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * LES LOGEMENTS ARRIVENT JUSQU'AUX APPLICATIONS.
 *
 * Les applications n'ont pas d'ecran natif pour la location entre membres : elles ouvrent le
 * module dans leur hote WebView, sans chrome, comme les 113 autres. C'est le mode de livraison de
 * ce depot, et le meme que celui des vehicules — en inventer un autre pour les seuls logements
 * ferait un demi-module natif.
 *
 * Trois choses doivent donc etre vraies, et aucune ne va de soi : le catalogue servi au mobile
 * annonce les ecrans, les pages repondent, et elles se rendent SANS la navigation du web — une
 * barre de menu dans un WebView donne deux navigations superposees.
 */
class LesLogementsArriventJusquALApplicationTest extends TestCase
{
    use RefreshDatabase;

    private const CHEMINS = [
        '/sejours',
        '/dashboard/location-entre-membres/mes-logements',
    ];

    public function test_le_catalogue_servi_au_mobile_annonce_les_deux_ecrans(): void
    {
        Sanctum::actingAs($this->membre());

        $reponse = $this->getJson(route('api.modules.index'))->assertOk();

        $chemins = collect($reponse->json('groups'))
            ->flatMap(fn (array $groupe): array => $groupe['modules'])
            ->pluck('path')
            ->all();

        foreach (self::CHEMINS as $chemin) {
            $this->assertContains($chemin, $chemins, "Le mobile n’annonce pas {$chemin}");
        }
    }

    /** TEMOIN — le balayage lit un vrai catalogue, pas une liste vide. */
    public function test_temoin_le_catalogue_annonce_aussi_les_vehicules(): void
    {
        Sanctum::actingAs($this->membre());

        $chemins = collect($this->getJson(route('api.modules.index'))->json('groups'))
            ->flatMap(fn (array $groupe): array => $groupe['modules'])
            ->pluck('path')
            ->all();

        $this->assertContains('/louer', $chemins);
        $this->assertGreaterThan(10, count($chemins));
    }

    /**
     * SANS CHROME DANS L'HOTE EMBARQUE.
     *
     * Une barre de navigation web dans un WebView donne deux navigations superposees : celle de
     * l'application et celle de la page.
     */
    public function test_les_deux_ecrans_se_rendent_sans_la_navigation_du_web(): void
    {
        $membre = $this->membre();
        PeerStay::factory()->publiee()->create();

        foreach (self::CHEMINS as $chemin) {
            $html = $this->actingAs($membre)->get($chemin.'?embed=1')
                ->assertOk()
                ->getContent();

            $this->assertStringNotContainsString('data-chrome="primary-nav"', (string) $html, $chemin);
        }
    }

    /** TEMOIN — hors de l'hote embarque, la meme page porte bien sa navigation. */
    public function test_temoin_hors_embarque_la_page_garde_sa_navigation(): void
    {
        $html = $this->actingAs($this->membre())
            ->get('/dashboard/location-entre-membres/mes-logements')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-chrome="primary-nav"', (string) $html);
    }

    private function membre(): User
    {
        return User::factory()->client()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ]);
    }
}
