<?php

namespace Tests\Feature;

use App\Livewire\Client\PrendreRendezVous;
use App\Models\Trade;
use App\Models\User;
use App\Services\Booking\CreateBookingAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

class BookingTradeFormAnswersIntegrationTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    protected function babysitterSchema(): array
    {
        return [
            'version' => 1,
            'fields' => [
                [
                    'key' => 'nb_enfants', 'label' => 'Nombre d\'enfants', 'type' => 'number',
                    'required' => true, 'min' => 1, 'max' => 10, 'default' => 1,
                    'pricing' => ['modifier' => 'per_unit', 'value' => 5],
                ],
                [
                    'key' => 'soir_tard', 'label' => 'Garde après 22h', 'type' => 'boolean',
                    'default' => false,
                    'pricing' => ['modifier' => 'percent', 'value' => 50],
                ],
            ],
        ];
    }

    public function test_create_booking_action_persists_trade_form_answers(): void
    {
        $context = $this->createCoverageContext();
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $employee = User::factory()->create([
            'role' => User::ROLE_EMPLOYE, 'is_active' => true,
            'primary_service_zone_id' => $context['zone']->id,
        ]);

        $action = app(CreateBookingAction::class);
        $rdv = $action->execute(
            client: $client,
            postal: $context['postalCode'],
            zone: $context['zone'],
            catalog: $context['service'],
            rule: $context['rule'],
            assignedEmployee: $employee,
            data: [
                'date' => now()->addDay()->toDateString(),
                'heure' => '10:00',
                'service_zone_id' => $context['zone']->id,
                'postal_code_id' => $context['postalCode']->id,
                'service_identifier' => $context['service']->code ?: $context['service']->slug,
                'type_lieu' => 'maison',
                'frequence' => 'ponctuel',
                'surface' => '50_100',
                'adresse' => '1 rue Test',
                'ville' => $context['postalCode']->city_name,
                'code_postal' => $context['postalCode']->code,
                'telephone_client' => '+32490000000',
                'priorite' => 'normale',
                'commentaire_client' => null,
                'options_prestation' => [],
                'zones_specifiques' => [],
                'materiel_specifique' => [],
                'is_recurrent' => false,
                'status' => 'en_attente',
                'booking_mode' => 'scheduled',
                'duree_estimee' => 90,
                'devis_estime' => 100.0,
                'employe_id' => $employee->id,
                'trade_form_answers' => [
                    'nb_enfants' => 3,
                    'soir_tard' => true,
                ],
            ],
        );

        $this->assertNotNull($rdv);
        $this->assertSame(['nb_enfants' => 3, 'soir_tard' => true], $rdv->fresh()->trade_form_answers);
    }

    public function test_selecting_a_service_with_trade_schema_loads_the_schema_in_livewire(): void
    {
        $context = $this->createCoverageContext();
        $trade = Trade::create([
            'slug' => 'babysitting', 'code' => 'BABY', 'name' => 'Babysitting',
            'is_active' => true, 'sort_order' => 10,
            'booking_form_schema' => $this->babysitterSchema(),
        ]);

        // Rattacher le service au trade Babysitting
        $context['service']->update(['trade_id' => $trade->id]);

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $this->actingAs($client);

        $code = $context['service']->code ?: $context['service']->slug;

        Livewire::test(PrendreRendezVous::class)
            ->set('postal_code_input', $context['postalCode']->code)
            ->set('selected_service_identifier', $code)
            ->assertCount('tradeFormSchema.fields', 2)
            ->assertSet('tradeFormAnswers.nb_enfants', 1)        // default
            ->assertSet('tradeFormAnswers.soir_tard', false);    // default
    }

    public function test_selecting_a_service_whose_trade_has_no_schema_clears_state(): void
    {
        $context = $this->createCoverageContext();
        $trade = Trade::create([
            'slug' => 'nettoyage', 'code' => 'CLEAN', 'name' => 'Nettoyage',
            'is_active' => true, 'sort_order' => 10,
            'booking_form_schema' => null,
        ]);

        $context['service']->update(['trade_id' => $trade->id]);

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $this->actingAs($client);

        $code = $context['service']->code ?: $context['service']->slug;

        Livewire::test(PrendreRendezVous::class)
            ->set('postal_code_input', $context['postalCode']->code)
            ->set('selected_service_identifier', $code)
            ->assertSet('tradeFormSchema', null)
            ->assertSet('tradeFormAnswers', []);
    }

    public function test_schema_validation_rules_are_merged_into_global_rules(): void
    {
        $context = $this->createCoverageContext();
        $trade = Trade::create([
            'slug' => 'babysitting', 'code' => 'BABY', 'name' => 'Babysitting',
            'is_active' => true, 'sort_order' => 10,
            'booking_form_schema' => $this->babysitterSchema(),
        ]);
        $context['service']->update(['trade_id' => $trade->id]);

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $this->actingAs($client);

        $code = $context['service']->code ?: $context['service']->slug;

        $component = Livewire::test(PrendreRendezVous::class)
            ->set('postal_code_input', $context['postalCode']->code)
            ->set('selected_service_identifier', $code);

        // Vérifier que les règles du schema sont bien intégrées
        // (accès à la méthode protected via Reflection)
        $instance = $component->instance();
        $methodName = 'rules';
        $rules = (new \ReflectionMethod($instance, $methodName))->invoke($instance);
        $this->assertArrayHasKey('tradeFormAnswers.nb_enfants', $rules);
        $this->assertContains('required', $rules['tradeFormAnswers.nb_enfants']);
        $this->assertContains('numeric', $rules['tradeFormAnswers.nb_enfants']);
        $this->assertContains('min:1', $rules['tradeFormAnswers.nb_enfants']);
        $this->assertContains('max:10', $rules['tradeFormAnswers.nb_enfants']);
    }

    public function test_price_delta_uses_devis_estime_as_base_for_percent_pricing(): void
    {
        $context = $this->createCoverageContext();
        $trade = Trade::create([
            'slug' => 'babysitting', 'code' => 'BABY', 'name' => 'Babysitting',
            'is_active' => true, 'sort_order' => 10,
            'booking_form_schema' => $this->babysitterSchema(),
        ]);
        $context['service']->update(['trade_id' => $trade->id]);

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $this->actingAs($client);

        $code = $context['service']->code ?: $context['service']->slug;

        $component = Livewire::test(PrendreRendezVous::class)
            ->set('postal_code_input', $context['postalCode']->code)
            ->set('selected_service_identifier', $code)
            ->set('devis_estime', 200.0)
            ->set('tradeFormAnswers.nb_enfants', 4)
            ->set('tradeFormAnswers.soir_tard', true);

        $delta = $component->get('tradeFormPriceDelta');
        // 4 × 5 (per_unit) = 20
        // 200 × 50% (percent) = 100
        // Total = 120
        $this->assertSame(120.0, $delta['total']);
    }
}
