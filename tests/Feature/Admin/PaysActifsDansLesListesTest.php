<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\GestionEquipesPartenaires;
use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Le drapeau `is_active` existait sur les pays ; la liste des equipes l'ignorait. */
class PaysActifsDansLesListesTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_pays_desactive_quitte_la_liste(): void
    {
        Country::query()->update(['is_active' => false]);

        $actif = Country::factory()->create(['name' => 'Pays Ouvert', 'is_active' => true]);
        $eteint = Country::factory()->create(['name' => 'Pays Ferme', 'is_active' => false]);

        $pays = Livewire::actingAs($this->prendreLeSiege())
            ->test(GestionEquipesPartenaires::class)
            ->get('countries');

        $noms = collect($pays)->pluck('name')->all();

        $this->assertContains('Pays Ouvert', $noms, 'un pays actif doit rester proposable');
        $this->assertNotContains('Pays Ferme', $noms, 'un pays eteint ne doit plus s’offrir');
        $this->assertNotNull($actif->id);
        $this->assertNotNull($eteint->id);
    }

    public function test_le_pays_par_defaut_d_une_equipe_neuve_est_actif(): void
    {
        Country::query()->update(['is_active' => false]);

        // Cree AVANT l'actif : sans tri ni filtre, c'est lui que `value('id')` rendait.
        Country::factory()->create(['name' => 'AAA Eteint', 'is_active' => false]);
        $actif = Country::factory()->create(['name' => 'ZZZ Ouvert', 'is_active' => true]);

        $composant = Livewire::actingAs($this->prendreLeSiege())
            ->test(GestionEquipesPartenaires::class)
            ->call('newTeam');

        $this->assertSame($actif->id, $composant->get('teamForm')['country_id'] ?? null);
    }
}
