<?php

namespace Tests\Feature\Rental;

use App\Models\User;
use Database\Seeders\ComptesDeDeveloppementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** LES COMPTES DE DÉVELOPPEMENT DOIVENT OUVRIR CE QU'ILS ANNONCENT, ET RIEN DE PLUS. */
class ComptesDeDeveloppementTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_compte_de_travail_ouvre_les_ecrans(): void
    {
        $this->seed(ComptesDeDeveloppementSeeder::class);

        $admin = User::query()->where('email', 'dev-admin@brio.test')->firstOrFail();

        $this->assertFalse((bool) $admin->is_super_admin,
            'Un super-administrateur passerait tous les gardes et masquerait le defaut qu’on veut voir.');

        $this->actingAs($admin);
        $this->get(route('admin.finance'))->assertSuccessful();
        $this->get(route('admin.rentals.center'))->assertSuccessful();
    }

    /** LE COMPTABLE N'ATTEINT QUE SA COMPTABILITÉ. */
    public function test_le_comptable_natteint_que_sa_comptabilite(): void
    {
        $this->seed(ComptesDeDeveloppementSeeder::class);

        $this->actingAs(User::query()->where('email', 'comptable@brio.test')->firstOrFail());

        $this->get(route('admin.accounting-v2.center'))->assertSuccessful();
        $this->get(route('admin.finance'))->assertForbidden();
        $this->get(route('admin.utilisateurs.manage'))->assertForbidden();
    }

    /** Et le comptoir de location n'ouvre que le sien. */
    public function test_le_comptoir_de_location_nouvre_que_le_sien(): void
    {
        $this->seed(ComptesDeDeveloppementSeeder::class);

        $this->actingAs(User::query()->where('email', 'locations@brio.test')->firstOrFail());

        $this->get(route('admin.rentals.center'))->assertSuccessful();
        $this->get(route('admin.accounting-v2.center'))->assertForbidden();
    }

    /** Tous atteignent leur tableau de bord : sans lui, ils seraient enfermés dehors. */
    public function test_chacun_atteint_son_tableau_de_bord(): void
    {
        $this->seed(ComptesDeDeveloppementSeeder::class);

        foreach (['dev-admin@brio.test', 'comptable@brio.test', 'locations@brio.test'] as $adresse) {
            $this->actingAs(User::query()->where('email', $adresse)->firstOrFail());
            $this->get(route('admin.dashboard'))->assertSuccessful();
        }
    }

    /** RELANCÉ, LE SEMIS N'ÉCRASE PAS UN MOT DE PASSE CHOISI. */
    public function test_le_semis_nefface_pas_un_mot_de_passe_change(): void
    {
        $this->seed(ComptesDeDeveloppementSeeder::class);

        $admin = User::query()->where('email', 'dev-admin@brio.test')->firstOrFail();
        $admin->forceFill(['password' => Hash::make('choisi-par-le-developpeur')])->save();

        $this->seed(ComptesDeDeveloppementSeeder::class);

        $this->assertTrue(
            Hash::check('choisi-par-le-developpeur', $admin->refresh()->password),
            'Le semis a réinitialisé un mot de passe que quelqu’un avait changé.',
        );
    }

    /** Et il ne double pas les comptes. */
    public function test_le_semis_est_idempotent(): void
    {
        $this->seed(ComptesDeDeveloppementSeeder::class);
        $avant = User::query()->count();

        $this->seed(ComptesDeDeveloppementSeeder::class);

        $this->assertSame($avant, User::query()->count());
    }
}
