<?php

namespace Tests\Feature\Admin;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Trade;
use App\Models\User;
use App\Support\Domain\QuestionType;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Le parcours de questions d'un métier, servi à l'application mobile. POURQUOI CETTE API EXISTE. */
class MobileJourneyBuilderTest extends TestCase
{
    use RefreshDatabase;

    private Trade $metier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);

        $this->metier = Trade::query()->has('questions')->firstOrFail();

        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'platform_role' => 'admin',
            'access_scope' => User::ACCESS_SCOPE_ALL,
        ]), ['*']);
    }

    public function test_il_sert_le_parcours_d_un_metier(): void
    {
        $reponse = $this->getJson("/api/admin/catalogue/trades/{$this->metier->id}/journey")->assertOk();

        $this->assertNotEmpty($reponse->json('data'));
        $this->assertSame($this->metier->name, $reponse->json('trade.name'));
    }

    public function test_chaque_question_porte_ses_options_et_leurs_prix(): void
    {
        $question = Question::query()
            ->where('trade_id', $this->metier->id)
            ->whereIn('type', [QuestionType::SINGLE_CHOICE, QuestionType::BOOLEAN])
            ->has('options')
            ->firstOrFail();

        $reponse = $this->getJson("/api/admin/catalogue/trades/{$this->metier->id}/journey")->assertOk();
        $servie = collect($reponse->json('data'))->firstWhere('id', $question->id);

        // Le prix d'une réponse est ce qu'on vient régler : le taire obligerait à ouvrir le web
        // pour savoir ce que coûte « oui ».
        $this->assertNotEmpty($servie['options']);
        $this->assertArrayHasKey('price_modifier_cents', $servie['options'][0]);
    }

    public function test_il_ajoute_une_question(): void
    {
        $this->postJson("/api/admin/catalogue/trades/{$this->metier->id}/questions", [
            'label' => 'Voulez-vous l’installation ?',
            'code' => 'installation',
            'type' => QuestionType::BOOLEAN,
        ])->assertCreated();

        $this->assertDatabaseHas('questions', [
            'trade_id' => $this->metier->id,
            'code' => 'installation',
        ]);
    }

    public function test_il_refuse_deux_questions_au_meme_code_sur_un_metier(): void
    {
        $existante = Question::query()->where('trade_id', $this->metier->id)->firstOrFail();

        $this->postJson("/api/admin/catalogue/trades/{$this->metier->id}/questions", [
            'label' => 'Doublon',
            'code' => $existante->code,
            'type' => QuestionType::TEXT,
        ])->assertStatus(422);
    }

    public function test_il_modifie_une_question(): void
    {
        $question = Question::query()->where('trade_id', $this->metier->id)->firstOrFail();

        $this->patchJson("/api/admin/catalogue/questions/{$question->id}", [
            'label' => 'Libellé revu',
            'is_required' => false,
        ])->assertOk();

        $this->assertSame('Libellé revu', $question->fresh()->label);
        $this->assertFalse((bool) $question->fresh()->is_required);
    }

    public function test_il_deplace_une_question_dans_le_parcours(): void
    {
        $ordre = Question::query()
            ->where('trade_id', $this->metier->id)
            ->orderBy('sort_order')->orderBy('id')
            ->pluck('id')->all();

        $this->postJson("/api/admin/catalogue/questions/{$ordre[1]}/move", ['direction' => -1])
            ->assertOk();

        // L'ordre EST le parcours.
        $apres = Question::query()
            ->where('trade_id', $this->metier->id)
            ->orderBy('sort_order')->orderBy('id')
            ->pluck('id')->all();

        $this->assertSame($ordre[1], $apres[0]);
    }

    public function test_il_ajoute_une_option_a_une_question(): void
    {
        $question = Question::query()
            ->where('trade_id', $this->metier->id)
            ->whereIn('type', [QuestionType::SINGLE_CHOICE, QuestionType::BOOLEAN])
            ->firstOrFail();

        $this->postJson("/api/admin/catalogue/questions/{$question->id}/options", [
            'label' => 'Oui, avec installation',
            'price_modifier_euros' => '150',
        ])->assertCreated();

        // Euros saisis, centimes stockés — le même piège que sur le web, et la même réponse.
        $this->assertDatabaseHas('question_options', [
            'question_id' => $question->id,
            'label' => 'Oui, avec installation',
            'price_modifier_cents' => 15000,
        ]);
    }

    public function test_il_regle_le_supplement_d_une_option(): void
    {
        $option = QuestionOption::query()
            ->whereHas('question', fn ($q) => $q->where('trade_id', $this->metier->id))
            ->firstOrFail();

        $this->patchJson("/api/admin/catalogue/options/{$option->id}", [
            'price_modifier_euros' => '12,50',
        ])->assertOk();

        // La virgule est la façon française d'écrire un prix ; la refuser serait hostile.
        $this->assertSame(1250, (int) $option->fresh()->price_modifier_cents);
    }

    public function test_il_supprime_une_option(): void
    {
        $option = QuestionOption::query()
            ->whereHas('question', fn ($q) => $q->where('trade_id', $this->metier->id))
            ->firstOrFail();

        $this->deleteJson("/api/admin/catalogue/options/{$option->id}")->assertOk();

        $this->assertNull(QuestionOption::find($option->id));
    }

    public function test_il_dit_ce_qui_empeche_la_publication(): void
    {
        $reponse = $this->getJson("/api/admin/catalogue/trades/{$this->metier->id}/journey")->assertOk();

        // Le verdict du validateur, servi avec le parcours.
        $this->assertIsArray($reponse->json('publication'));
        $this->assertArrayHasKey('can_publish', $reponse->json('publication'));
    }

    public function test_il_publie_un_parcours_valide(): void
    {
        $reponse = $this->postJson("/api/admin/catalogue/trades/{$this->metier->id}/publish");

        // Publiable ou non, le serveur tranche — mais il ne doit jamais rendre une erreur serveur.
        $this->assertContains($reponse->status(), [200, 409]);
    }

    public function test_un_lecteur_seul_ne_touche_pas_au_parcours(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin', 'platform_role' => 'admin', 'access_scope' => 'readonly',
        ]), ['*']);

        $question = Question::query()->where('trade_id', $this->metier->id)->firstOrFail();
        $avant = $question->label;

        $this->patchJson("/api/admin/catalogue/questions/{$question->id}", ['label' => 'Interdit'])
            ->assertForbidden();

        $this->assertSame($avant, $question->fresh()->label);
    }

    public function test_un_non_admin_est_refuse(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'client']), ['*']);

        $this->getJson("/api/admin/catalogue/trades/{$this->metier->id}/journey")->assertForbidden();
    }
}
