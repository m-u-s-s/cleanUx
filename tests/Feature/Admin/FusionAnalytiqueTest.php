<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\AnalyticsCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FusionAnalytiqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_trois_url_fusionnees_atterrissent_sur_le_bon_onglet(): void
    {
        $admin = $this->prendreLeSiege();

        foreach ([
            '/admin/analytics' => 'onglet=ensemble',
            '/admin/analytics-v2' => 'onglet=usage',
            '/admin/nps' => 'onglet=nps',
        ] as $ancienne => $attendu) {
            $reponse = $this->actingAs($admin)->get($ancienne);
            $reponse->assertRedirect();
            $this->assertStringContainsString($attendu, $reponse->headers->get('Location'), $ancienne);
            $this->assertStringContainsString('/admin/analytics/exploration', $reponse->headers->get('Location'), $ancienne);
        }
    }

    public function test_les_quatre_onglets_rendent(): void
    {
        $admin = $this->prendreLeSiege();

        // Chaque onglet porte une preuve qui n'appartient qu'a lui : rendre sans erreur
        // ne dirait pas si les trois pages fusionnees ont bien suivi.
        $preuves = [
            'metier' => 'Filtres analytics',
            'ensemble' => 'CA par mois',
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
}
