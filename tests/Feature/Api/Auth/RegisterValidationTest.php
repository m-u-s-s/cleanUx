<?php

namespace Tests\Feature\Api\Auth;

use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ce que l'inscription accepte réellement.
 *
 * Deux champs étaient collectés sans le moindre contrôle : `vat_number` (`nullable|max:32`) et
 * `trade_answers` (`nullable|array`). Le premier part ensuite à l'INSEE et à VIES lors de la
 * vérification KYB, le second alimente le matching. Une saisie fautive n'échouait donc pas devant
 * l'utilisateur mais plusieurs jours plus tard, à la revue du dossier.
 */
class RegisterValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_company_cannot_register_with_a_bogus_business_number(): void
    {
        $this->postJson('/api/auth/register', $this->payload([
            'account_type' => 'provider',
            'provider_kind' => 'company',
            'company_name' => 'Sans Clé SPRL',
            'vat_number' => 'BE0000000000',
        ]))->assertStatus(422)->assertJsonValidationErrors('vat_number');

        $this->assertSame(0, User::count());
    }

    public function test_a_real_business_number_is_accepted(): void
    {
        $this->postJson('/api/auth/register', $this->payload([
            'account_type' => 'provider',
            'provider_kind' => 'company',
            'company_name' => 'Proximus',
            'vat_number' => 'BE 0202.239.951',
        ]))->assertCreated();
    }

    /** Un indépendant n'en fournit pas : le champ reste facultatif. */
    public function test_no_business_number_is_still_fine(): void
    {
        $this->postJson('/api/auth/register', $this->payload([
            'account_type' => 'provider',
            'provider_kind' => 'independent',
        ]))->assertCreated();
    }

    public function test_a_required_trade_answer_cannot_be_left_out(): void
    {
        $trade = $this->tradeWithSchema();

        $this->postJson('/api/auth/register', $this->payload([
            'account_type' => 'provider',
            'trade_id' => $trade->id,
            'trade_answers' => ['has_own_equipment' => true],
        ]))->assertStatus(422)->assertJsonValidationErrors('trade_answers.experience_years');
    }

    public function test_a_numeric_answer_outside_its_bounds_is_rejected(): void
    {
        $trade = $this->tradeWithSchema();

        $this->postJson('/api/auth/register', $this->payload([
            'account_type' => 'provider',
            'trade_id' => $trade->id,
            'trade_answers' => $this->answersFor($trade, ['experience_years' => 200]),
        ]))->assertStatus(422)->assertJsonValidationErrors('trade_answers.experience_years');
    }

    public function test_a_non_numeric_answer_to_a_number_question_is_rejected(): void
    {
        $trade = $this->tradeWithSchema();

        $this->postJson('/api/auth/register', $this->payload([
            'account_type' => 'provider',
            'trade_id' => $trade->id,
            'trade_answers' => $this->answersFor($trade, ['experience_years' => 'beaucoup']),
        ]))->assertStatus(422)->assertJsonValidationErrors('trade_answers.experience_years');
    }

    public function test_complete_answers_are_accepted(): void
    {
        $trade = $this->tradeWithSchema();

        $this->postJson('/api/auth/register', $this->payload([
            'account_type' => 'provider',
            'trade_id' => $trade->id,
            'trade_answers' => $this->answersFor($trade),
        ]))->assertCreated();
    }

    /** L'app cliente n'envoie pas de métier : aucune règle par question ne doit apparaître. */
    public function test_a_client_registration_is_untouched_by_the_trade_rules(): void
    {
        $this->postJson('/api/auth/register', $this->payload())->assertCreated();
    }

    private function tradeWithSchema(): Trade
    {
        $trade = Trade::query()->create([
            'code' => 'peinture_test',
            'name' => 'Peinture',
            'slug' => 'peinture-test',
            'is_active' => true,
            'provider_form_schema' => ['fields' => [
                ['key' => 'experience_years', 'type' => 'number', 'label' => "Années d'expérience", 'required' => true, 'min' => 0, 'max' => 60],
                ['key' => 'insurance_company', 'type' => 'text', 'label' => 'Assureur', 'required' => true],
                ['key' => 'has_own_equipment', 'type' => 'boolean', 'label' => 'Matériel propre', 'required' => false],
            ]],
        ]);

        return $trade;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function answersFor(Trade $trade, array $overrides = []): array
    {
        return array_merge([
            'experience_years' => 5,
            'insurance_company' => 'AG Insurance',
            'has_own_equipment' => true,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nouveau Prestataire',
            'email' => 'nouveau@prestataire.test',
            'password' => 'motdepasse123',
            'password_confirmation' => 'motdepasse123',
            'accept_terms' => true,
        ], $overrides);
    }
}
