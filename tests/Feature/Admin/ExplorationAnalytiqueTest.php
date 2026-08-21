<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\AnalyticsCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L'EXPLORATION ANALYTIQUE MÉTIER — un module complet que rien n'atteignait.
 *
 * `App\Livewire\Admin\AnalyticsCenter` porte 346 lignes : filtres par zone,
 * service, intervenant, marché, statut et période, analyses croisées, carte de
 * chaleur, tendance mensuelle et export CSV. Il n'avait ni route ni montage.
 *
 * Ce qui l'avait rendu invisible : un HOMONYME. `App\Livewire\Admin\Analytics\
 * AnalyticsCenter`, lui, est routé sur `/admin/analytics-v2` et fait tout autre
 * chose (sessions, entonnoir d'usage). Chercher « AnalyticsCenter » dans les
 * routes répondait donc « c'est routé » — pour l'autre classe.
 */
class ExplorationAnalytiqueTest extends TestCase
{
    use RefreshDatabase;

    /** TÉMOIN POSITIF — un administrateur habilité atteint l'écran. */
    public function test_un_administrateur_habilite_atteint_l_exploration(): void
    {
        $admin = User::factory()->adminComplet()->create();

        $this->actingAs($admin)
            ->get(route('admin.analytics.exploration'))
            ->assertOk();
    }

    /** TÉMOIN POSITIF — le composant se monte et rend ses données. */
    public function test_le_composant_se_monte(): void
    {
        $admin = User::factory()->adminComplet()->create();

        Livewire::actingAs($admin)
            ->test(AnalyticsCenter::class)
            ->assertOk();
    }

    /** REFUS — sans la capacité d'analytique, la porte reste fermée. */
    public function test_un_administrateur_sans_la_capacite_est_refuse(): void
    {
        $restreint = User::factory()->admin()->create([
            'permissions' => ['manage-quality'],
        ]);

        $this->actingAs($restreint)
            ->get(route('admin.analytics.exploration'))
            ->assertForbidden();
    }

    /** La tuile du répertoire mène à une route qui existe. */
    public function test_la_tuile_mene_a_la_route(): void
    {
        $tuile = collect(config('modules.catalogue'))
            ->firstWhere('route', 'admin.analytics.exploration');

        $this->assertNotNull($tuile, 'La tuile est absente de config/modules.php');
        $this->assertSame('admin', $tuile['context']);
        $this->assertSame('manage-analytics', $tuile['gate'] ?? null);
        $this->assertTrue(Route::has($tuile['route']));
    }

    /** L'homonyme reste distinct : deux écrans, deux routes, deux usages. */
    public function test_l_homonyme_reste_distinct(): void
    {
        $this->assertTrue(Route::has('admin.analytics.center'), 'Analytics v2 doit rester routé');
        $this->assertNotSame(
            route('admin.analytics.center'),
            route('admin.analytics.exploration'),
            'Les deux AnalyticsCenter ne doivent pas se disputer la même adresse'
        );
    }
}
