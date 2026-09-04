<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\AnalyticsCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FusionAnalytiqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_deux_url_fusionnees_atterrissent_sur_le_bon_onglet(): void
    {
        $admin = $this->prendreLeSiege();

        foreach ([
            '/admin/analytics-v2' => 'onglet=usage',
            '/admin/nps' => 'onglet=nps',
        ] as $ancienne => $attendu) {
            $reponse = $this->actingAs($admin)->get($ancienne);
            $reponse->assertRedirect();
            $this->assertStringContainsString($attendu, $reponse->headers->get('Location'), $ancienne);
            $this->assertStringContainsString('/admin/analytics/exploration', $reponse->headers->get('Location'), $ancienne);
        }
    }

    public function test_l_ancienne_page_analytics_conduit_au_tableau_de_bord(): void
    {
        // Son onglet a rejoint la section « Plateforme » : l'URL suit son contenu.
        $this->actingAs($this->prendreLeSiege())
            ->get('/admin/analytics')
            ->assertRedirect('/admin/dashboard');
    }

    public function test_les_trois_onglets_rendent(): void
    {
        $admin = $this->prendreLeSiege();

        // Chaque onglet porte une preuve qui n'appartient qu'a lui : rendre sans erreur
        // ne dirait pas si les pages fusionnees ont bien suivi.
        $preuves = [
            'metier' => 'Filtres analytics',
            'usage' => 'Entonnoir',
            'nps' => 'Score NPS',
        ];

        $this->assertSame(array_keys(AnalyticsCenter::ONGLETS), array_keys($preuves));

        foreach ($preuves as $onglet => $preuve) {
            Livewire::actingAs($admin)
                ->test(AnalyticsCenter::class, ['onglet' => $onglet])
                ->assertOk()
                ->assertSet('onglet', $onglet)
                ->assertSee($preuve);
        }
    }

    public function test_un_onglet_invente_retombe_sur_le_metier(): void
    {
        Livewire::actingAs($this->prendreLeSiege())
            ->test(AnalyticsCenter::class, ['onglet' => 'nimporte-quoi'])
            ->assertSet('onglet', 'metier');
    }

    public function test_l_ancien_onglet_ensemble_retombe_aussi(): void
    {
        // Temoin du retrait : `?onglet=ensemble` circule encore dans de vieux liens.
        Livewire::actingAs($this->prendreLeSiege())
            ->test(AnalyticsCenter::class, ['onglet' => 'ensemble'])
            ->assertSet('onglet', 'metier');
    }
}
