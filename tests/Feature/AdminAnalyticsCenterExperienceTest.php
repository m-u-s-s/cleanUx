<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAnalyticsCenterExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function administrateurDesChiffres(): User
    {
        return User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => ['manage-analytics'],
        ]);
    }

    public function test_admin_can_render_analytics_center(): void
    {
        // `/admin/analytics` a rejoint la section « Plateforme » du tableau de bord.
        $response = $this->actingAs($this->administrateurDesChiffres())
            ->followingRedirects()
            ->get('/admin/analytics');

        $response->assertOk();
        $response->assertSee('Santé de la plateforme');
        $response->assertSee('CA total');
        $response->assertSee('Marge plateforme');
    }

    public function test_les_deux_lectures_annoncees_par_l_ancienne_page_existent_toujours(): void
    {
        // L'ancienne page annoncait « Mix marché » et « KPIs par zone » sans les rendre :
        // c'etaient deux pancartes. Les tableaux, eux, vivent sur l'onglet metier.
        $response = $this->actingAs($this->administrateurDesChiffres())
            ->get('/admin/analytics/exploration?onglet=metier');

        $response->assertOk();
        $response->assertSee('KPIs par zone');
        $response->assertSee('Part entreprise');
    }
}
