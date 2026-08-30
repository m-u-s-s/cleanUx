<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\AgendaHebdomadaire;
use App\Livewire\Admin\PlanningAdmin;
use App\Models\Booking;
use App\Models\User;
use App\Support\Domain\BookingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L'AGENDA HEBDOMADAIRE — ce que son affichage doit tenir.
 *
 * Il rendait sept colonnes de 153 px avec la carte LARGE du focus : badges par-dessus
 * l'heure, service invisible, et un fond `bg-slate-50/70` que la transmutation du verre
 * ne reprend pas — encre claire sur surface claire, 1,3:1 mesurés le 2026-08-30.
 */
class AgendaHebdomadaireAffichageTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const VUES = [
        'views/livewire/admin/agenda-hebdomadaire.blade.php',
        'views/components/rdv-agenda-card.blade.php',
    ];

    private function admin(): User
    {
        return User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'is_active' => true,
        ]);
    }

    /**
     * LE MODIFICATEUR D'OPACITÉ ÉCHAPPE AUX LISTES DE `glass.css`.
     *
     * Ces listes énumèrent des noms de classe littéraux — `.bg-white`, `.bg-white/90`,
     * `.bg-slate-50`… Un `bg-slate-50/70` ou un `bg-emerald-50/70` n'y figure pas : la
     * surface reste CLAIRE en mode sombre pendant que l'encre, elle, passe au clair.
     * Sous un préfixe `dark:`, la variante est explicite et le piège n'existe pas.
     */
    public function test_aucune_surface_a_modificateur_d_opacite_sans_variante_sombre(): void
    {
        $fuites = [];

        foreach (self::VUES as $vue) {
            // Les commentaires citent les classes fautives pour expliquer le piège : les
            // scanner reviendrait à mesurer la prose. Le premier passage a échoué là-dessus.
            $source = (string) preg_replace(
                ['/\{\{--.*?--\}\}/s', '/\/\*.*?\*\//s'],
                '',
                (string) file_get_contents(resource_path($vue))
            );

            preg_match_all('/[\w:\/\[\]\.-]*bg-[\w-]+\/\d+/', $source, $trouves);

            foreach ($trouves[0] as $classe) {
                if (! str_contains($classe, 'dark:')) {
                    $fuites[] = basename($vue).' : '.$classe;
                }
            }
        }

        $this->assertSame([], $fuites, "Ces fonds resteront clairs en mode sombre :\n".implode("\n", $fuites));
    }

    /** La carte large ne revient pas dans une colonne de 153 px. */
    public function test_l_agenda_emploie_la_carte_compacte(): void
    {
        $source = (string) file_get_contents(resource_path(self::VUES[0]));

        $this->assertStringContainsString('x-rdv-agenda-card', $source);
        $this->assertStringNotContainsString('x-rdv-planning-card', $source);
    }

    /**
     * LA MODALE SORT DE LA SECTION DE VERRE, ET C'EST STRUCTUREL.
     *
     * `backdrop-filter` fait de la section le bloc conteneur de tout `position: fixed`
     * descendant : le fond de modale y faisait 507 px au lieu des 917 de la fenêtre —
     * panneau décentré, débordant par le bas, page à peine assombrie (mesuré le 2026-08-30).
     * Et la condition doit rester DEHORS : `@teleport` rend un `<template x-teleport>`
     * qu'Alpine clone à son initialisation ; émis vide, le clone le reste.
     */
    public function test_la_modale_est_teleportee_hors_de_la_section_de_verre(): void
    {
        $source = (string) file_get_contents(resource_path(self::VUES[0]));

        $this->assertMatchesRegularExpression(
            '/@if\(\$rdvOuvert\)\s*@teleport\(\'body\'\)/',
            $source,
            'La modale doit être téléportée dans le corps, et la condition posée AVANT le téléport.'
        );
    }

    /**
     * LA NAVIGATION DE SEMAINE VISE DES METHODES QUI EXISTENT.
     *
     * Un `wire:click` qui nomme une methode absente ne fait RIEN, sans erreur visible :
     * c'est la famille de defauts la plus couteuse de ce depot.
     */
    public function test_la_section_porte_une_navigation_de_semaine_branchee(): void
    {
        $section = (string) file_get_contents(
            resource_path('views/livewire/admin/planning/weekly-agenda.blade.php')
        );

        foreach (['semainePrecedente', 'semaineSuivante', 'allerAujourdHui'] as $methode) {
            $this->assertStringContainsString(
                'wire:click="'.$methode.'"',
                $section,
                "La navigation de semaine n'appelle pas {$methode}."
            );

            $this->assertTrue(
                method_exists(PlanningAdmin::class, $methode),
                "PlanningAdmin ne porte pas {$methode} : le bouton ne ferait rien."
            );
        }
    }

    /**
     * TEMOIN — les sept jours et le contenu d'une mission sont bien rendus. Sans lui, les
     * deux tests ci-dessus resteraient verts sur une vue vidée de sa substance.
     */
    public function test_temoin_les_sept_jours_et_leur_contenu_sont_rendus(): void
    {
        $lundi = now()->startOfWeek();

        $rdv = Booking::factory()->create([
            'date' => $lundi->copy()->addDay()->toDateString(),
            'heure' => '09:00:00',
            'status' => BookingStatus::CONFIRME,
        ]);

        $composant = Livewire::actingAs($this->admin())
            ->test(AgendaHebdomadaire::class, ['semaine' => $lundi->toDateString()])
            ->assertOk();

        foreach (range(0, 6) as $decalage) {
            $composant->assertSee($lundi->copy()->addDays($decalage)->translatedFormat('D d/m'), escape: false);
        }

        $composant->assertSee($rdv->service_display_name)
            ->assertSee('09:00')
            // Le statut EN TOUTES LETTRES : la barre de couleur ne le porte pas seule.
            ->assertSee('Confirmé', escape: false);
    }
}
