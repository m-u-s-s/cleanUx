<?php

namespace Tests\Feature\DesignSystem;

use App\Livewire\Admin\Trades;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * « LE FORMULAIRE DU CATALOGUE EST TROP LONG — PRIVILEGIER UN WIZARD. »
 *
 * Le formulaire d'un metier portait vingt-quatre champs a plat, dont sept drapeaux et un
 * schema JSON. On le remplissait en faisant defiler, sans jamais savoir combien il en
 * restait. Les quatre groupes existaient deja dans le balisage ; ils deviennent des etapes.
 *
 * AUCUN CHAMP NE QUITTE LE DOM : `x-show` masque, il ne demonte pas. C'est ce que ce test
 * verifie — un assistant qui monterait ses etapes a la demande perdrait les valeurs saisies
 * au premier retour en arriere, et l'enregistrement ne porterait que l'etape visible.
 */
class LAssistantDuMetierTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create([
            'permissions' => ['manage-services', 'perform-critical-admin-actions'],
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'is_active' => true,
        ]);
    }

    private function formulaire(): string
    {
        $this->actingAs($this->admin());

        return Livewire::test(Trades::class)->call('openCreate')->html();
    }

    public function test_le_formulaire_porte_quatre_etapes(): void
    {
        $rendu = $this->formulaire();

        $this->assertStringContainsString('brio-rail', $rendu);

        foreach (['Identité', 'Tarifs', 'Questionnaire', 'Règles'] as $titre) {
            $this->assertStringContainsString($titre, $rendu, $titre);
        }
    }

    /**
     * TEMOIN — LES VINGT-QUATRE CHAMPS SONT LA, TOUS, DANS LE MEME RENDU.
     *
     * C'est le controle qui distingue un assistant d'une amputation. Un decoupage qui ne
     * rendrait que l'etape courante passerait le test precedent — et l'enregistrement
     * viderait en silence les champs des trois autres.
     */
    public function test_temoin_tous_les_champs_restent_dans_le_rendu(): void
    {
        $rendu = $this->formulaire();

        $champs = [
            'name', 'slug', 'code', 'sort_order', 'icon', 'color', 'short_description', 'description',
            'default_hourly_rate', 'emergency_multiplier', 'night_multiplier', 'weekend_multiplier',
            'quote_validity_days', 'sla_response_minutes', 'hourly_billing', 'requires_quote_by_default',
            'booking_form_schema_json',
            'is_active', 'requires_certification', 'requires_insurance_proof', 'requires_face_check',
            'is_personal_default', 'taxi_rules',
        ];

        foreach ($champs as $champ) {
            $this->assertStringContainsString('"'.$champ.'"', $rendu, "Le champ « {$champ} » a disparu du rendu.");
        }
    }

    /**
     * TEMOIN — l'enregistrement porte encore TOUT le formulaire.
     *
     * La preuve par le comportement, pas par le balisage : un metier cree depuis
     * l'assistant doit garder les valeurs des quatre etapes.
     */
    public function test_temoin_un_metier_cree_garde_les_quatre_etapes(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(Trades::class)
            ->call('openCreate')
            ->set('name', 'Ramonage')
            ->set('slug', 'ramonage-essai')
            ->set('code', 'RAMESSAI')
            ->set('default_hourly_rate', 42)
            ->set('requires_certification', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('trades', [
            'slug' => 'ramonage-essai',
            'requires_certification' => true,
        ]);
    }

    /**
     * TEMOIN — une etape en faute se signale dans le rail.
     *
     * Sans cela, un refus de validation sur l'identite alors qu'on est rendu aux drapeaux
     * ressemble a un bouton qui ne fait rien.
     */
    public function test_temoin_une_etape_en_faute_se_voit(): void
    {
        $this->actingAs($this->admin());

        $rendu = Livewire::test(Trades::class)
            ->call('openCreate')
            ->set('name', '')
            ->call('save')
            ->assertHasErrors('name')
            ->html();

        $this->assertStringContainsString('data-en-faute', $rendu);
    }
}
