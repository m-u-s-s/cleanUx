<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\Admin\OrderEngine\QuestionnaireBuilder;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Trade;
use App\Models\User;
use App\Support\Domain\QuestionType;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Éditer une option de réponse — libellé, prix, multiplicateur, durée, défaut.
 *
 * LE TROU QUE ÇA COMBLE. Le constructeur savait AJOUTER une option — elle s'appelait « Nouvelle
 * réponse » et valait 0 € — et l'AFFICHER, mais rien ne permettait de la renommer ni de lui donner
 * un prix. `updateOption()` existait dans le composant et n'était appelée par aucune vue.
 *
 * POURQUOI ÇA COMPTE. C'est le seul chemin pour un supplément conditionnel : « Voulez-vous
 * l'installation ? Oui / Non », où seul « Oui » ajoute 150 €. Le mode `add` posé sur la QUESTION ne
 * convient pas — il ajoute son montant dès que la question est répondue, donc aussi quand on
 * répond « Non ». Le prix doit vivre sur l'OPTION.
 */
class QuestionOptionEditingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function optionSurUnBooleen(): QuestionOption
    {
        $trade = Trade::query()->firstOrFail();

        $question = Question::create([
            'trade_id' => $trade->id,
            'code' => 'installation',
            'label' => 'Voulez-vous l’installation ?',
            'type' => QuestionType::BOOLEAN,
            'is_required' => true,
            'sort_order' => 99,
            'is_active' => true,
        ]);

        return QuestionOption::create([
            'question_id' => $question->id,
            'label' => 'Nouvelle réponse',
            'value' => 'oui',
            'sort_order' => 0,
            'is_default' => false,
        ]);
    }

    public function test_il_renomme_une_option(): void
    {
        $option = $this->optionSurUnBooleen();

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $option->question->trade])
            ->call('updateOption', $option->id, ['label' => 'Oui, avec installation']);

        // Sans cela, toute option ajoutée reste « Nouvelle réponse » jusqu'à la fin des temps.
        $this->assertSame('Oui, avec installation', $option->fresh()->label);
    }

    public function test_il_pose_un_supplement_en_euros_et_le_stocke_en_centimes(): void
    {
        $option = $this->optionSurUnBooleen();

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $option->question->trade])
            ->call('updateOption', $option->id, ['price_modifier_euros' => '150']);

        /*
         * L'administrateur saisit des EUROS, la base stocke des CENTIMES.
         *
         * C'est le bug classique de ce genre d'écran : 150 saisis, 150 centimes enregistrés, et un
         * supplément de 1,50 € que personne ne remarque avant la première facture.
         */
        $this->assertSame(15000, (int) $option->fresh()->price_modifier_cents);
    }

    public function test_il_accepte_les_centimes_dans_la_saisie(): void
    {
        $option = $this->optionSurUnBooleen();

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $option->question->trade])
            ->call('updateOption', $option->id, ['price_modifier_euros' => '12,50']);

        // La virgule est la façon française d'écrire un prix ; la refuser serait hostile.
        $this->assertSame(1250, (int) $option->fresh()->price_modifier_cents);
    }

    public function test_il_accepte_un_supplement_negatif(): void
    {
        $option = $this->optionSurUnBooleen();

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $option->question->trade])
            ->call('updateOption', $option->id, ['price_modifier_euros' => '-20']);

        // Une option peut RETIRER du prix — « sans produits », « je fournis le matériel ».
        $this->assertSame(-2000, (int) $option->fresh()->price_modifier_cents);
    }

    public function test_il_vide_le_supplement_quand_le_champ_est_vide(): void
    {
        $option = $this->optionSurUnBooleen();
        $option->update(['price_modifier_cents' => 5000]);

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $option->question->trade])
            ->call('updateOption', $option->id, ['price_modifier_euros' => '']);

        $this->assertSame(0, (int) $option->fresh()->price_modifier_cents);
    }

    public function test_il_regle_le_multiplicateur_et_la_duree(): void
    {
        $option = $this->optionSurUnBooleen();

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $option->question->trade])
            ->call('updateOption', $option->id, ['price_multiplier' => '1.25', 'duration_modifier_min' => '30']);

        $this->assertSame(1.25, (float) $option->fresh()->price_multiplier);
        $this->assertSame(30, (int) $option->fresh()->duration_modifier_min);
    }

    public function test_poser_un_defaut_retire_l_autre(): void
    {
        $option = $this->optionSurUnBooleen();
        $autre = QuestionOption::create([
            'question_id' => $option->question_id,
            'label' => 'Non',
            'value' => 'non',
            'sort_order' => 1,
            'is_default' => true,
        ]);

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $option->question->trade])
            ->call('updateOption', $option->id, ['is_default' => true]);

        // Deux défauts feraient dépendre l'écran client de l'ordre de tri, et le validateur
        // refuserait la publication pour une raison invisible à l'écran.
        $this->assertTrue((bool) $option->fresh()->is_default);
        $this->assertFalse((bool) $autre->fresh()->is_default);
    }

    public function test_il_refuse_de_deplacer_une_option_vers_une_autre_question(): void
    {
        $option = $this->optionSurUnBooleen();
        $ailleurs = Question::query()->where('id', '!=', $option->question_id)->firstOrFail();

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $option->question->trade])
            ->call('updateOption', $option->id, ['question_id' => $ailleurs->id]);

        /*
         * `question_id` est `fillable`, et la méthode recevait un tableau LIBRE venu du navigateur.
         * Un appel forgé aurait déplacé l'option vers une question d'un autre métier — la
         * commande la citerait sans qu'elle apparaisse nulle part dans son parcours.
         */
        $this->assertSame($option->question_id, $option->fresh()->question_id);
    }

    public function test_il_refuse_un_multiplicateur_absurde(): void
    {
        $option = $this->optionSurUnBooleen();

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $option->question->trade])
            ->call('updateOption', $option->id, ['price_multiplier' => '999']);

        // Un facteur 999 sur un prix n'est pas une intention, c'est une faute de frappe.
        $this->assertNotSame(999.0, (float) $option->fresh()->price_multiplier);
    }

    public function test_la_vue_offre_bien_les_champs(): void
    {
        $option = $this->optionSurUnBooleen();

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $option->question->trade])
            ->assertOk()
            ->assertSee('Supplément')
            // La méthode existait sans être appelée par aucune vue : c'est ce qui rendait tout
            // l'écran inutilisable pour un supplément conditionnel.
            ->assertSeeHtml('updateOption');
    }
}
