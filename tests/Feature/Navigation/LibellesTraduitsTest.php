<?php

namespace Tests\Feature\Navigation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * LA NAVIGATION DOIT PARLER LA LANGUE DU VISITEUR.
 *
 * Les libellés de `config/modules.php` sortaient bruts — `{{ $link['label'] }}` — donc
 * toujours en français, quelle que soit la langue choisie. Sur une plateforme belge servie
 * en français, néerlandais et anglais, un client anglophone lisait « Historique » à côté de
 * « New booking » : la moitié de l'écran traduite, l'autre non.
 *
 * Mesuré au moment de la correction : 189 libellés de tuiles sur 193 n'avaient aucune
 * traduction anglaise. Poser `__()` ne les invente pas — une clé absente s'affiche
 * inchangée — mais rend enfin applicables celles qui existent, et celles qu'on ajoutera.
 */
class LibellesTraduitsTest extends TestCase
{
    use RefreshDatabase;

    /** TÉMOIN — en français, rien ne bouge. */
    public function test_temoin_le_francais_reste_inchange(): void
    {
        App::setLocale('fr');

        $this->assertSame('Ma journée', __('Ma journée'));
    }

    /** Les libellés du catalogue sont bien des clés de traduction connues. */
    public function test_un_libelle_du_catalogue_se_traduit(): void
    {
        App::setLocale('en');
        $this->assertSame('My day', __('Ma journée'));

        App::setLocale('nl');
        $this->assertSame('Mijn dag', __('Ma journée'));
    }

    /**
     * L'INVARIANT — la vue passe le libellé par la traduction.
     *
     * Sans ce contrôle, quelqu'un remettrait `{{ $link['label'] }}` un jour, et la
     * navigation redeviendrait monolingue sans qu'aucun test ne s'en aperçoive : les
     * libellés français continueraient de s'afficher, identiques.
     */
    public function test_les_vues_passent_les_libelles_par_la_traduction(): void
    {
        $vues = [
            resource_path('views/navigation-menu.blade.php'),
            resource_path('views/livewire/shared/modules-directory.blade.php'),
        ];

        // Toutes les vues fautives d'un coup : un libelle non traduit se recopie d'une vue a
        // l'autre, et corriger la premiere ne dit rien des suivantes.
        $brutes = [];

        foreach ($vues as $vue) {
            $contenu = (string) file_get_contents($vue);

            foreach (['{{ $link[\'label\'] }}', '{{ $module[\'label\'] }}'] as $motif) {
                if (str_contains($contenu, $motif)) {
                    $brutes[] = basename($vue).' : '.$motif;
                }
            }
        }

        $this->assertSame([], $brutes, 'Ces libelles ne passent pas par __() : ils resteront en francais.');
    }

    /** Un utilisateur anglophone voit la navigation en anglais, pas un mélange. */
    public function test_un_prestataire_anglophone_voit_sa_navigation_traduite(): void
    {
        $prestataire = User::factory()->employe()->create(['locale' => 'en']);

        $this->actingAs($prestataire)
            ->get(route('employe.dashboard'))
            ->assertOk()
            ->assertSee('My day');
    }
}
