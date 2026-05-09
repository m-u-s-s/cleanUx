<?php

namespace Tests\Feature;

use App\Livewire\Client\PrendreRendezVous;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

class ZoneAwareStructuredReservationTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    public function test_booking_persists_structured_snapshots_and_normalizes_city_from_resolved_postal_code(): void
    {
        $context = $this->createCoverageContext();
        $client = User::factory()->client()->create();
        $employee = User::factory()->employe()->create();
        $bookingDate = now()->addDays(2)->toDateString();

        $this->assignEmployeeToZone($employee, $context['zone'], [], ['date' => $bookingDate]);

        $this->actingAs($client);

        Livewire::test(PrendreRendezVous::class)
            ->set('selected_service_identifier', $context['service']->code ?: $context['service']->slug)
            ->set('type_lieu', 'appartement')
            ->set('frequence', 'ponctuel')
            ->set('surface', 'moins_50')
            ->set('adresse', 'Rue de la Loi 1')
            ->set('ville', 'Ville erronée')
            ->set('postal_code_input', $context['postalCode']->code)
            ->set('telephone_client', '0470000000')
            ->set('priorite', 'normale')
            ->set('rdvDate', $bookingDate)
            ->set('rdvHeure', '10:00')
            ->call('validerRdv')
            ->assertHasNoErrors();

        $rdv = Booking::query()->firstOrFail();

        $this->assertSame($context['postalCode']->city_name, $rdv->ville);
        $this->assertSame($context['postalCode']->code, $rdv->code_postal);
        $this->assertSame($context['zone']->id, $rdv->service_zone_id);
        $this->assertSame($context['service']->id, $rdv->service_catalog_id);
        $this->assertSame($context['postalCode']->id, $rdv->postal_code_id);

        $this->assertSame('postal_code', data_get($rdv->zone_snapshot, 'resolution.source'));
        $this->assertSame($context['zone']->id, data_get($rdv->zone_snapshot, 'zone.id'));
        $this->assertSame($context['postalCode']->id, data_get($rdv->zone_snapshot, 'postal_code.id'));
        $this->assertSame($context['zone']->name, data_get($rdv->zone_snapshot, 'zone_name'));

        $this->assertSame($context['service']->id, data_get($rdv->pricing_snapshot, 'service.id'));
        $this->assertSame($context['service']->name, data_get($rdv->pricing_snapshot, 'service.name'));
        $this->assertSame(
            $context['service']->code ?: $context['service']->slug,
            data_get($rdv->pricing_snapshot, 'service_identifier')
        );
        $this->assertSame($context['rule']->id, data_get($rdv->pricing_snapshot, 'rule.id'));
        $this->assertFalse((bool) data_get($rdv->pricing_snapshot, 'requires_manual_validation'));
        $this->assertSame($context['service']->name, data_get($rdv->pricing_snapshot, 'service_name'));
    }

    public function test_service_catalog_can_be_resolved_by_code_when_service_type_is_missing(): void
    {
        $context = $this->createCoverageContext([
            'service' => [
                'service_type' => null,
                'code' => 'WINDOWS-PRO',
                'name' => 'Nettoyage vitres premium',
                'slug' => 'nettoyage-vitres-premium',
            ],
        ]);

        $client = User::factory()->client()->create();
        $employee = User::factory()->employe()->create();
        $bookingDate = now()->addDays(2)->toDateString();

        $this->assignEmployeeToZone($employee, $context['zone'], [], ['date' => $bookingDate]);

        $this->actingAs($client);

        Livewire::test(PrendreRendezVous::class)
            ->set('selected_service_identifier', $context['service']->code)
            ->set('type_lieu', 'appartement')
            ->set('frequence', 'ponctuel')
            ->set('surface', 'moins_50')
            ->set('adresse', 'Rue de la Loi 1')
            ->set('ville', $context['postalCode']->city_name)
            ->set('postal_code_input', $context['postalCode']->code)
            ->set('telephone_client', '0470000000')
            ->set('priorite', 'normale')
            ->set('rdvDate', $bookingDate)
            ->set('rdvHeure', '10:00')
            ->call('validerRdv')
            ->assertHasNoErrors();

        $rendezVous = \App\Models\Booking::query()
            ->where('service_catalog_id', $context['service']->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($rendezVous);
        $this->assertSame($context['service']->id, $rendezVous->service_catalog_id);
        $this->assertNull($rendezVous->getRawOriginal('service_type'));
        $this->assertSame(
            $context['service']->code ?: $context['service']->slug,
            data_get($rendezVous->pricing_snapshot, 'service_identifier')
                ?: data_get($rendezVous->pricing_snapshot, 'service.service_identifier')
                ?: $rendezVous->service_identifier
        );
    }

    public function test_entreprise_booking_uses_selected_site_as_resolution_source_and_normalized_location(): void
    {
        $context = $this->createCoverageContext();
        $account = OrganizationAccount::factory()->create([
            'postal_code_id' => $context['postalCode']->id,
            'country_id' => $context['country']->id,
            'region_id' => $context['region']->id,
            'province_id' => $context['province']->id,
            'commune_id' => $context['commune']->id,
            'city' => $context['postalCode']->city_name,
            'postal_code' => $context['postalCode']->code,
        ]);
        $client = User::factory()->entreprise()->create([
            'organization_account_id' => $account->id,
        ]);
        $site = OrganizationSite::factory()->create([
            'organization_account_id' => $account->id,
            'client_user_id' => $client->id,
            'service_zone_id' => $context['zone']->id,
            'postal_code_id' => $context['postalCode']->id,
            'address_line_1' => 'Avenue Louise 200',
            'city' => $context['postalCode']->city_name,
            'postal_code' => $context['postalCode']->code,
            'is_active' => true,
        ]);
        $employee = User::factory()->employe()->create();
        $bookingDate = now()->addDays(2)->toDateString();

        $this->assignEmployeeToZone($employee, $context['zone'], [], ['date' => $bookingDate]);

        $this->actingAs($client);

        Livewire::test(PrendreRendezVous::class)
            ->set('selected_service_identifier', $context['service']->code ?: $context['service']->slug)
            ->set('type_lieu', 'bureau')
            ->set('frequence', 'ponctuel')
            ->set('surface', 'moins_50')
            ->set('organization_site_id', $site->id)
            ->set('adresse', 'Adresse temporaire')
            ->set('ville', 'Ville temporaire')
            ->set('postal_code_input', '9999')
            ->set('telephone_client', '0470000000')
            ->set('priorite', 'normale')
            ->set('site_contact_name', 'Reception CleanUx')
            ->set('rdvDate', $bookingDate)
            ->set('rdvHeure', '10:00')
            ->call('validerRdv')
            ->assertHasNoErrors();

        $rdv = Booking::query()->firstOrFail();

        $this->assertSame($site->id, $rdv->organization_site_id);
        $this->assertSame('Avenue Louise 200', $rdv->adresse);
        $this->assertSame($context['postalCode']->city_name, $rdv->ville);
        $this->assertSame($context['postalCode']->code, $rdv->code_postal);
        $this->assertSame('organization_site', data_get($rdv->zone_snapshot, 'resolution.source'));
        $this->assertSame($site->id, data_get($rdv->zone_snapshot, 'organization_site.id'));
    }

    public function test_entreprise_booking_rejects_site_from_another_organization(): void
    {
        $context = $this->createCoverageContext();
        $primaryAccount = OrganizationAccount::factory()->create();
        $otherAccount = OrganizationAccount::factory()->create();

        $client = User::factory()->entreprise()->create([
            'organization_account_id' => $primaryAccount->id,
        ]);

        $foreignSite = OrganizationSite::factory()->create([
            'organization_account_id' => $otherAccount->id,
            'service_zone_id' => $context['zone']->id,
            'postal_code_id' => $context['postalCode']->id,
            'is_active' => true,
        ]);

        $employee = User::factory()->employe()->create();
        $bookingDate = now()->addDays(2)->toDateString();
        $this->assignEmployeeToZone($employee, $context['zone'], [], ['date' => $bookingDate]);

        $this->actingAs($client);

        Livewire::test(PrendreRendezVous::class)
            ->set('selected_service_identifier', $context['service']->code ?: $context['service']->slug)
            ->set('type_lieu', 'bureau')
            ->set('frequence', 'ponctuel')
            ->set('surface', 'moins_50')
            ->set('organization_site_id', $foreignSite->id)
            ->set('adresse', 'Rue de la Loi 1')
            ->set('ville', $context['postalCode']->city_name)
            ->set('postal_code_input', $context['postalCode']->code)
            ->set('telephone_client', '0470000000')
            ->set('priorite', 'normale')
            ->set('rdvDate', $bookingDate)
            ->set('rdvHeure', '10:00')
            ->call('validerRdv')
            ->assertHasErrors(['organization_site_id']);

        $this->assertDatabaseCount('rendez_vous', 0);
    }
}
