<?php

namespace Tests\Feature\Provider;

use App\Livewire\Provider\Onboarding\ProviderOnboardingWizard;
use App\Models\Trade;
use App\Models\User;
use App\Services\Catalog\ProviderCoverageWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L'ASSISTANT WEB PROPOSAIT HUIT MÉTIERS QUI N'EXISTAIENT PAS.
 *
 * `cleaning_residential`, `cleaning_office`, `plumbing`, `electrical`, `gardening`, `moving`,
 * `handyman`, `painting` — écrits en dur dans le composant. Mesuré : AUCUN des huit n'est un code
 * de `trades`, qui en compte seize (`CLN`, `PNT`, `PLB`…). L'écran natif, lui, lit le catalogue
 * depuis toujours (`useTrades`).
 *
 * Un prestataire inscrit par le web déclarait donc des métiers qui ne correspondaient à rien.
 */
class LAssistantWebOffreLeVraiCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private function prestataire(): User
    {
        return User::factory()->employe()->create();
    }

    public function test_l_assistant_propose_exactement_le_catalogue_actif(): void
    {
        $actifs = Trade::factory()->count(3)->create(['is_active' => true]);
        $retire = Trade::factory()->create(['is_active' => false]);

        $offerts = Livewire::actingAs($this->prestataire())
            ->test(ProviderOnboardingWizard::class)
            ->viewData('metiers')
            ->pluck('id')
            ->all();

        foreach ($actifs as $metier) {
            $this->assertContains($metier->id, $offerts, "Le métier « {$metier->name} » manque à l’assistant.");
        }

        $this->assertNotContains($retire->id, $offerts, 'Un métier retiré du catalogue est encore proposé.');
    }

    /**
     * LE GARDE. Ce que la case porte doit se résoudre : c'est la valeur envoyée à `setSkills`, et
     * donc ce qui décide de la couverture. Une liste réécrite en dur retomberait ici.
     */
    public function test_chaque_valeur_proposee_designe_un_metier_reel(): void
    {
        Trade::factory()->count(3)->create(['is_active' => true]);

        $slugs = Livewire::actingAs($this->prestataire())
            ->test(ProviderOnboardingWizard::class)
            ->viewData('metiers')
            ->pluck('slug')
            ->all();

        $this->assertNotSame([], $slugs, 'L’assistant ne propose aucun métier : ce test ne mesurerait plus rien.');

        $this->assertSame(
            count($slugs),
            Trade::query()->whereIn('slug', $slugs)->count(),
            'Une valeur proposée ne désigne aucun métier du catalogue.'
        );
    }

    /** L'écran doit montrer ce qui décide des missions reçues, pas un champ d'affichage. */
    public function test_les_metiers_deja_couverts_sont_pre_coches(): void
    {
        $user = $this->prestataire();
        $metiers = Trade::factory()->count(2)->create(['is_active' => true]);

        app(ProviderCoverageWriter::class)->sync($user, [$metiers[0]->id], []);

        Livewire::actingAs($user)
            ->test(ProviderOnboardingWizard::class)
            ->assertSet('selectedSkills', [$metiers[0]->slug]);
    }

    /** Son témoin : sans couverture, rien n'est pré-coché — on ne coche pas au hasard. */
    public function test_temoin_sans_couverture_rien_n_est_pre_coche(): void
    {
        Trade::factory()->count(2)->create(['is_active' => true]);

        Livewire::actingAs($this->prestataire())
            ->test(ProviderOnboardingWizard::class)
            ->assertSet('selectedSkills', []);
    }
}
