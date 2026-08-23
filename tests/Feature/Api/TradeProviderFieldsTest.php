<?php

namespace Tests\Feature\Api;

use App\Models\Trade;
use Database\Seeders\ProviderTradeQuestionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Questions métier consultables AVANT authentification. */
class TradeProviderFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_questions_are_readable_without_authentication(): void
    {
        $trade = $this->tradeWithQuestions(['requires_certification' => false, 'requires_insurance_proof' => false]);

        $response = $this->getJson("/api/trades/{$trade->id}/provider-fields")->assertOk();

        $response->assertJsonPath('trade.id', $trade->id);
        $keys = array_column($response->json('fields'), 'key');
        $this->assertContains('experience_years', $keys);
        $this->assertContains('intervention_radius_km', $keys);
    }

    /** Les questions réglementaires découlent des drapeaux du métier plutôt que d'une liste tenue à la main : un métier certifié demande sa référence, un métier assuré sa police. */
    public function test_a_regulated_trade_asks_for_its_certification_and_insurance(): void
    {
        $trade = $this->tradeWithQuestions(['requires_certification' => true, 'requires_insurance_proof' => true]);

        $keys = array_column(
            $this->getJson("/api/trades/{$trade->id}/provider-fields")->assertOk()->json('fields'),
            'key'
        );

        $this->assertContains('certification_reference', $keys);
        $this->assertContains('insurance_policy_number', $keys);
    }

    public function test_an_unregulated_trade_asks_neither(): void
    {
        $trade = $this->tradeWithQuestions(['requires_certification' => false, 'requires_insurance_proof' => false]);

        $keys = array_column(
            $this->getJson("/api/trades/{$trade->id}/provider-fields")->assertOk()->json('fields'),
            'key'
        );

        $this->assertNotContains('certification_reference', $keys);
        $this->assertNotContains('insurance_policy_number', $keys);
    }

    /** Un métier sans schéma ne doit pas casser le formulaire : il rend simplement une liste vide. */
    public function test_a_trade_without_a_schema_returns_an_empty_field_list(): void
    {
        $trade = Trade::factory()->create(['provider_form_schema' => null]);

        $this->getJson("/api/trades/{$trade->id}/provider-fields")
            ->assertOk()
            ->assertJsonPath('fields', []);
    }

    /**
     * @param  array<string, bool>  $flags
     */
    private function tradeWithQuestions(array $flags): Trade
    {
        $trade = Trade::factory()->create(array_merge($flags, ['provider_form_schema' => null]));

        $this->seed(ProviderTradeQuestionsSeeder::class);

        return $trade->fresh();
    }
}
