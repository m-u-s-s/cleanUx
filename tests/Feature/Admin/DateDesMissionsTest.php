<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\MissionsAdmin;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * `join()` PREND SON SEPARATEUR POUR UNE COLONNE quand le premier element est un objet.
 *
 * `collect([$rdv->date, $rdv->heure])->join(' à ')` faisait donc `pluck(' à ')` sur un Carbon :
 * deux `null`, et les treize cartes annoncaient « Date non renseignée » sur des dates pleines.
 */
class DateDesMissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_carte_montre_la_date_et_l_heure(): void
    {
        Booking::factory()->create([
            'date' => '2026-09-20',
            'heure' => '08:30:00',
        ]);

        Livewire::actingAs($this->prendreLeSiege())
            ->test(MissionsAdmin::class)
            ->assertSee('20/09/2026 à 08:30', escape: false)
            ->assertDontSee('Date non renseignée');
    }

    /** TEMOIN POSITIF : sans date, la carte le dit — le repli existe toujours. */
    public function test_temoin_une_mission_sans_date_le_dit(): void
    {
        Booking::factory()->create(['date' => null, 'heure' => null]);

        Livewire::actingAs($this->prendreLeSiege())
            ->test(MissionsAdmin::class)
            ->assertSee('Date non renseignée');
    }
}
