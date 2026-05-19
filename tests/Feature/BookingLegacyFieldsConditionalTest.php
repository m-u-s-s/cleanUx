<?php

namespace Tests\Feature;

use App\Livewire\Client\PrendreRendezVous;
use App\Models\Trade;
use App\Models\User;
use Database\Seeders\TradeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

class BookingLegacyFieldsConditionalTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    public function test_nettoyage_trade_is_seeded_with_a_complete_form_schema(): void
    {
        $this->seed(TradeSeeder::class);

        $nettoyage = Trade::where('slug', 'nettoyage')->firstOrFail();

        $this->assertNotNull($nettoyage->booking_form_schema);
        $this->assertSame(1, $nettoyage->booking_form_schema['version']);

        $keys = collect($nettoyage->booking_form_schema['fields'])->pluck('key')->all();
        // Tous les champs cleaning historiques sont bien dans le schema
        $this->assertContains('type_lieu', $keys);
        $this->assertContains('surface', $keys);
        $this->assertContains('frequence', $keys);
        $this->assertContains('options_prestation', $keys);
        $this->assertContains('zones_specifiques', $keys);
        $this->assertContains('presence_animaux', $keys);
        $this->assertContains('acces_parking', $keys);
        $this->assertContains('materiel_fournit', $keys);
    }

    public function test_all_reference_trades_have_a_booking_form_schema_after_seed(): void
    {
        $this->seed(TradeSeeder::class);

        foreach (['nettoyage', 'batiment', 'peinture', 'levage', 'jardinage'] as $slug) {
            $trade = Trade::where('slug', $slug)->firstOrFail();
            $this->assertNotNull($trade->booking_form_schema,
                "Le trade [$slug] doit avoir un booking_form_schema (Phase F3 : aucun trade ne doit retomber sur le formulaire cleaning legacy).");
            $this->assertNotEmpty($trade->booking_form_schema['fields'] ?? [],
                "Le trade [$slug] doit avoir au moins un champ dans son schema.");
        }
    }

    public function test_legacy_rules_become_nullable_when_trade_has_schema(): void
    {
        $context = $this->createCoverageContext();
        $trade = Trade::create([
            'slug' => 'with-schema', 'code' => 'WS', 'name' => 'With Schema',
            'is_active' => true, 'sort_order' => 10,
            'booking_form_schema' => [
                'version' => 1,
                'fields' => [
                    ['key' => 'foo', 'label' => 'Foo', 'type' => 'text'],
                ],
            ],
        ]);
        $context['service']->update(['trade_id' => $trade->id]);

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $this->actingAs($client);

        $code = $context['service']->code ?: $context['service']->slug;

        $component = Livewire::test(PrendreRendezVous::class)
            ->set('postal_code_input', $context['postalCode']->code)
            ->set('selected_service_identifier', $code);

        $instance = $component->instance();
        $methodName = 'rules';
        $rules = (new \ReflectionMethod($instance, $methodName))->invoke($instance);

        $this->assertContains('nullable', $rules['type_lieu']);
        $this->assertContains('nullable', $rules['frequence']);
        $this->assertContains('nullable', $rules['surface']);
        $this->assertNotContains('required', $rules['type_lieu']);
        $this->assertNotContains('required', $rules['frequence']);
        $this->assertNotContains('required', $rules['surface']);
    }

    public function test_legacy_rules_remain_required_when_trade_has_no_schema(): void
    {
        $context = $this->createCoverageContext();
        // Pas de schema sur le service → comportement legacy
        $context['service']->trade_id = null;
        $context['service']->save();

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $this->actingAs($client);

        $code = $context['service']->code ?: $context['service']->slug;

        $component = Livewire::test(PrendreRendezVous::class)
            ->set('postal_code_input', $context['postalCode']->code)
            ->set('selected_service_identifier', $code);

        $instance = $component->instance();
        $methodName = 'rules';
        $rules = (new \ReflectionMethod($instance, $methodName))->invoke($instance);

        $this->assertContains('required', $rules['type_lieu']);
        $this->assertContains('required', $rules['frequence']);
        $this->assertContains('required', $rules['surface']);
    }

    public function test_validate_only_step1_skips_legacy_when_schema_present(): void
    {
        $context = $this->createCoverageContext();
        $trade = Trade::create([
            'slug' => 'with-schema-2', 'code' => 'WS2', 'name' => 'WS2',
            'is_active' => true, 'sort_order' => 10,
            'booking_form_schema' => [
                'version' => 1,
                'fields' => [['key' => 'a', 'label' => 'A', 'type' => 'text']],
            ],
        ]);
        $context['service']->update(['trade_id' => $trade->id]);

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $this->actingAs($client);

        $code = $context['service']->code ?: $context['service']->slug;

        // type_lieu/frequence/surface non remplis MAIS schema actif → step 1 OK
        Livewire::test(PrendreRendezVous::class)
            ->set('postal_code_input', $context['postalCode']->code)
            ->set('selected_service_identifier', $code)
            ->set('step', 1)
            ->call('nextStep')
            ->assertHasNoErrors(['type_lieu', 'frequence', 'surface']);
    }

    public function test_validate_only_step1_still_blocks_legacy_when_no_schema(): void
    {
        $context = $this->createCoverageContext();
        $context['service']->trade_id = null;
        $context['service']->save();

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $this->actingAs($client);

        $code = $context['service']->code ?: $context['service']->slug;

        // Pas de schema → champs requis
        Livewire::test(PrendreRendezVous::class)
            ->set('postal_code_input', $context['postalCode']->code)
            ->set('selected_service_identifier', $code)
            ->set('step', 1)
            ->call('nextStep')
            ->assertHasErrors(['type_lieu', 'frequence', 'surface']);
    }
}
