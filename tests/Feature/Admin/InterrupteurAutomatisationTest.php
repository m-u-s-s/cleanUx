<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\AutomationCenter;
use App\Models\FeatureFlagOverride;
use App\Models\User;
use App\Services\FeatureFlag\FeatureFlagService;
use App\Support\Platform\PorteDuSiege;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** La page annonçait le moteur éteint sans donner le moyen de l'allumer. */
class InterrupteurAutomatisationTest extends TestCase
{
    use RefreshDatabase;

    /** @param  list<string>  $capacites */
    private function admin(array $capacites): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        PorteDuSiege::ouvrir(fn () => $admin->forceFill([
            'platform_role' => 'admin',
            'permissions' => $capacites,
        ])->save());

        return $admin->refresh();
    }

    public function test_l_administrateur_allume_puis_eteint_le_moteur(): void
    {
        $admin = $this->admin(['manage-automation']);

        $this->assertFalse(app(FeatureFlagService::class)->isEnabled('automation'),
            'Témoin : le moteur part éteint, c’est le défaut du fichier de configuration.');

        Livewire::actingAs($admin)->test(AutomationCenter::class)->call('basculerLeMoteur');

        $this->assertTrue(FeatureFlagOverride::where('flag_key', 'automation')->value('is_enabled'));

        Livewire::actingAs($admin)->test(AutomationCenter::class)->call('basculerLeMoteur');

        $this->assertFalse((bool) FeatureFlagOverride::where('flag_key', 'automation')->value('is_enabled'));
    }

    public function test_sans_la_capacite_l_ecran_se_ferme(): void
    {
        // `boot()` refuse des le montage — et `boot()` s'execute AUSSI sur `/livewire/update`,
        // ou aucun intermediaire de route ne rejoue. La bascule est donc hors d'atteinte.
        $this->actingAs($this->admin(['manage-analytics']))
            ->get('/admin/automation')
            ->assertForbidden();

        $this->assertNull(FeatureFlagOverride::where('flag_key', 'automation')->first());
    }

    /** TEMOIN POSITIF : avec la capacite, la meme porte s'ouvre. */
    public function test_temoin_la_capacite_ouvre_l_ecran(): void
    {
        $this->actingAs($this->admin(['manage-automation']))
            ->get('/admin/automation')
            ->assertSuccessful()
            ->assertSee('Allumer le moteur');
    }
}
