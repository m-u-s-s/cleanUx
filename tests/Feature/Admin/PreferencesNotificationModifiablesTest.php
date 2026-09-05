<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\NotificationPreferences\NotificationPreferencesCenter;
use App\Models\NotificationPreference;
use App\Models\NotificationPreferenceAudit;
use App\Models\User;
use App\Support\Platform\PorteDuSiege;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** L'ecran n'avait qu'une methode, `render` : il regardait sans pouvoir corriger. */
class PreferencesNotificationModifiablesTest extends TestCase
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

    private function preference(bool $autorisee): NotificationPreference
    {
        return NotificationPreference::query()->create([
            'user_id' => User::factory()->create()->id,
            'channel' => 'email',
            'category' => 'marketing',
            'is_allowed' => $autorisee,
            'source' => NotificationPreference::SOURCE_USER,
            'version' => 1,
            'last_changed_at' => now(),
        ]);
    }

    public function test_l_administrateur_coupe_une_preference_et_la_trace_le_dit(): void
    {
        $preference = $this->preference(true);
        $admin = $this->admin(['manage-compliance']);

        Livewire::actingAs($admin)
            ->test(NotificationPreferencesCenter::class)
            ->call('basculerLaPreference', $preference->id);

        $this->assertFalse((bool) $preference->fresh()->is_allowed);

        // LA TRACE NOMME L'ACTEUR : une correction faite POUR quelqu'un n'est pas son choix.
        $this->assertDatabaseHas('notification_preference_audits', [
            'user_id' => $preference->user_id,
            'channel' => 'email',
            'category' => 'marketing',
            'source' => NotificationPreference::SOURCE_ADMIN,
            'actor_user_id' => $admin->id,
        ]);
    }

    /** TEMOIN POSITIF : le meme geste rouvre ce qui etait coupe. */
    public function test_temoin_l_administrateur_rouvre_une_preference(): void
    {
        $preference = $this->preference(false);

        Livewire::actingAs($this->admin(['manage-compliance']))
            ->test(NotificationPreferencesCenter::class)
            ->call('basculerLaPreference', $preference->id);

        $this->assertTrue((bool) $preference->fresh()->is_allowed);
    }

    public function test_sans_la_capacite_rien_ne_bouge(): void
    {
        $preference = $this->preference(true);

        // Joignable par `/livewire/update` sans qu'aucun bouton existe : la garde est dans la methode.
        Livewire::actingAs($this->admin(['manage-analytics']))
            ->test(NotificationPreferencesCenter::class)
            ->call('basculerLaPreference', $preference->id)
            ->assertForbidden();

        $this->assertTrue((bool) $preference->fresh()->is_allowed);
        $this->assertSame(0, NotificationPreferenceAudit::query()->count());
    }
}
