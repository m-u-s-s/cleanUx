<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\OrderEngine\OrderJourney;
use App\Models\Trade;
use App\Support\Pricing\HourlyRuleText;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LE SÉLECTEUR D'HEURES — un écran qui n'existait pas.
 *
 * Les méthodes vivaient sur le composant depuis le premier lot : `choisirLesHeures`,
 * `ajouterUneDemiHeure`, `heuresParDefaut`. AUCUNE VUE NE LES APPELAIT. PHPStan était vert, la
 * suite était verte, le composant répondait parfaitement à qui l'interrogeait en PHP — et le
 * client, lui, ne pouvait pas choisir ses heures. Une prestation horaire partait sur la durée par
 * défaut sans que personne ne l'ait décidée.
 *
 * C'est le piège que ce dépôt connaît sous le nom d'écran orphelin, transposé au web : rien dans
 * l'outillage ne demande « un utilisateur peut-il atteindre ce bouton ? ».
 *
 * CES TESTS MONTENT LE COMPOSANT ET REGARDENT CE QUI EST RENDU. C'est la seule mesure qui réponde
 * à cette question-là.
 */
class SelecteurDHeuresTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
    }

    public function test_le_selecteur_apparait_sur_un_metier_horaire(): void
    {
        $metier = $this->metierHoraire();

        Livewire::test(OrderJourney::class)
            ->call('selectTrade', $metier->id)
            ->assertSee('Combien de temps ?')
            ->assertSeeHtml('wire:click="ajouterUneDemiHeure"')
            ->assertSeeHtml('wire:click="retirerUneDemiHeure"');
    }

    /**
     * LA RÈGLE EST ANNONCÉE LÀ OÙ L'ON DÉCIDE, avec ses vrais chiffres.
     *
     * Une majoration qu'on découvre sur sa facture est un litige. Et elle doit porter les nombres
     * de la configuration : un « ×1,30 » tapé dans une phrase survivrait à un changement de
     * réglage, et la plateforme afficherait une règle qu'elle n'applique plus.
     */
    public function test_la_regle_du_depassement_est_annoncee_avec_ses_chiffres(): void
    {
        config()->set('order_engine.overtime_multiplier', 1.45);
        config()->set('order_engine.overtime_grace_minutes', 20);

        $metier = $this->metierHoraire();

        Livewire::test(OrderJourney::class)
            ->call('selectTrade', $metier->id)
            ->assertSee('1,45')
            ->assertSee('20 minutes');
    }

    /** TÉMOIN : un métier au forfait n'affiche aucun sélecteur, et surtout aucune règle horaire. */
    public function test_un_metier_au_forfait_naffiche_pas_le_selecteur(): void
    {
        $metier = $this->metierHoraire();
        $metier->forceFill(['hourly_billing' => false])->save();

        Livewire::test(OrderJourney::class)
            ->call('selectTrade', $metier->id)
            ->assertDontSee('Combien de temps ?')
            ->assertDontSeeHtml('wire:click="ajouterUneDemiHeure"');
    }

    public function test_ajouter_et_retirer_deplacent_les_heures(): void
    {
        $metier = $this->metierHoraire();

        $composant = Livewire::test(OrderJourney::class)->call('selectTrade', $metier->id);

        $depart = $composant->get('heuresChoisies') ?? $composant->instance()->heuresParDefaut();

        $composant->call('ajouterUneDemiHeure')
            ->assertSet('heuresChoisies', $depart + 0.5)
            ->call('retirerUneDemiHeure')
            ->assertSet('heuresChoisies', $depart);
    }

    /**
     * LES BORNES SONT CELLES DU COMPOSANT.
     *
     * Un doigt resté sur « + » ne doit pas produire un devis que personne ne peut honorer, et
     * descendre sous le plancher vendrait un déplacement à perte — le prestataire refuserait.
     */
    public function test_les_bornes_tiennent(): void
    {
        config()->set('order_engine.hourly_min_hours', 1.0);
        config()->set('order_engine.hourly_max_hours', 3.0);

        $metier = $this->metierHoraire();

        $composant = Livewire::test(OrderJourney::class)->call('selectTrade', $metier->id);

        for ($i = 0; $i < 20; $i++) {
            $composant->call('ajouterUneDemiHeure');
        }
        $composant->assertSet('heuresChoisies', 3.0);

        for ($i = 0; $i < 20; $i++) {
            $composant->call('retirerUneDemiHeure');
        }
        $composant->assertSet('heuresChoisies', 1.0);
    }

    /**
     * LE PAS VIENT DE LA CONFIGURATION, et c'est le même que celui de la prolongation.
     *
     * Il était codé en dur ici. Deux constantes séparées auraient fini par diverger — on aurait
     * commandé par demi-heures et prolongé par quarts d'heure, sur la même prestation.
     */
    public function test_le_pas_suit_la_configuration(): void
    {
        config()->set('order_engine.hourly_step_hours', 1.0);

        $metier = $this->metierHoraire();

        $composant = Livewire::test(OrderJourney::class)->call('selectTrade', $metier->id);
        $depart = $composant->get('heuresChoisies') ?? $composant->instance()->heuresParDefaut();

        $composant->call('ajouterUneDemiHeure')->assertSet('heuresChoisies', $depart + 1.0);
    }

    /** La règle rendue porte les chiffres, jamais les jetons bruts. */
    public function test_la_regle_ne_laisse_aucun_jeton_brut(): void
    {
        foreach (['fr', 'nl', 'en'] as $langue) {
            foreach ([HourlyRuleText::courte($langue), HourlyRuleText::complete($langue), HourlyRuleText::prestataire($langue)] as $texte) {
                $this->assertStringNotContainsString(':multiplier', $texte, "Jeton non remplacé en {$langue}.");
                $this->assertStringNotContainsString(':grace', $texte, "Jeton non remplacé en {$langue}.");
                $this->assertNotSame('', trim($texte), "Texte vide en {$langue}.");
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────

    private function metierHoraire(): Trade
    {
        $metier = Trade::where('slug', 'peinture')->firstOrFail();

        $metier->forceFill([
            'hourly_billing' => true,
            'default_hourly_rate' => 45,
        ])->save();

        return $metier->refresh();
    }
}
