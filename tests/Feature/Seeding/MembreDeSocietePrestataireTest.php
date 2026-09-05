<?php

namespace Tests\Feature\Seeding;

use App\Enums\ProviderType;
use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
use App\Models\User;
use Database\Seeders\DemoPlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Rejoindre une societe prestataire doit changer `provider_type`.
 *
 * `isProviderCompanyWorker()` — la garde de tout l'espace societe prestataire — ne lit ni
 * l'adhesion ni le type de la societe, mais `provider_profiles.provider_type`.
 */
class MembreDeSocietePrestataireTest extends TestCase
{
    use RefreshDatabase;

    public function test_aucun_membre_actif_ne_reste_type_independant(): void
    {
        $this->seed(DemoPlatformSeeder::class);

        $societes = OrganizationAccount::query()
            ->whereIn('type', ['provider_company', 'provider_solo', 'hybrid'])
            ->pluck('id');

        $membres = DB::table('organization_members')
            ->whereIn('organization_account_id', $societes)
            ->where('status', 'active')
            ->pluck('user_id')
            ->unique();

        // TEMOIN DE PORTEE : sans membre, l'assertion serait vraie pour rien.
        $this->assertGreaterThan(0, $membres->count(), 'La demonstration doit produire des membres de societe.');

        $enfermes = User::query()
            ->whereIn('id', $membres)
            ->with('providerProfile')
            ->get()
            ->reject(fn (User $u) => $u->providerProfile === null)
            ->reject(fn (User $u) => $u->isProviderCompanyWorker())
            ->pluck('email')
            ->all();

        $this->assertSame([], $enfermes,
            'Ces membres de societe prestataire recevraient 403 sur leur propre espace : '.implode(', ', $enfermes));
    }

    /** TEMOIN — un prestataire SANS societe reste independant, et c'est voulu. */
    public function test_temoin_un_independant_le_reste(): void
    {
        $solo = User::factory()->employe()->create();
        ProviderProfile::factory()->create([
            'user_id' => $solo->id,
            'provider_type' => ProviderType::INDEPENDENT->value,
        ]);

        $this->assertFalse($solo->refresh()->isProviderCompanyWorker());
    }
}
