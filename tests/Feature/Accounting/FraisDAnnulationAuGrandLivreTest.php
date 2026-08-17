<?php

namespace Tests\Feature\Accounting;

use App\Models\AccountingEntry;
use App\Models\Booking;
use App\Services\Payments\MissionPaymentService;
use App\Services\Payments\Webhooks\StripeWebhookHandlers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\Support\Spine\SpineScenario;
use Tests\TestCase;

/**
 * DES FRAIS D'ANNULATION SONT DE L'ARGENT ENCAISSÉ — ILS DOIVENT ENTRER DANS UN LIVRE.
 *
 * LE DÉFAUT. `handlePaymentIntentSucceeded` ne postait au grand livre que sur
 * `payment_status === 'captured'`. Une capture de frais pose `fee_captured`, un statut distinct et
 * délibéré — sans lui, le prestataire serait crédité de la part d'une prestation jamais rendue. La
 * même garde écartait donc du même geste l'écriture comptable, qui, elle, devait avoir lieu. On
 * prélevait de l'argent réel sur la carte du client et le grand livre n'en savait rien.
 *
 * Tout le reste était prêt et n'attendait qu'un appelant : le plan comptable déclare
 * `708 Produits annexes (frais d'annulation)`, et `ChartOfAccounts::salesAccount('cancellation_fee')`
 * le renvoie déjà. Le motif habituel de ce dépôt — la pièce existe, elle n'est branchée nulle part.
 *
 * ── CE QUI REND CES TESTS DÉLICATS ────────────────────────────────────────────────────────────
 *
 * `BookingAutoPoster` attrape `\Throwable` et se contente d'un avertissement au journal. Un compte
 * inconnu, une écriture déséquilibrée, une ligne à zéro : `AccountingService::post()` lève, et
 * l'appelant n'en saura jamais rien. Un test qui se contenterait de « aucune exception » serait
 * donc vert sur un module entièrement muet. On assère ici l'ÉCRITURE, ligne par ligne.
 *
 * Et le postage automatique est coupé par défaut (`auto_post_enabled`), à dessein : la compta doit
 * valider les écritures avant la mise en ligne. Chaque test l'allume explicitement — sans quoi il
 * mesurerait ce réglage et rien d'autre.
 */
class FraisDAnnulationAuGrandLivreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('accounting_v2.auto_post_enabled', true);
    }

    /**
     * LE DÉFAUT LUI-MÊME : les frais atteignent le compte 708.
     */
    public function test_des_frais_encaisses_produisent_une_ecriture(): void
    {
        $reservation = $this->reservationDontLesFraisSontCaptures(fraisCents: 2400);

        $this->recevoirLeWebhook($reservation, ['application_fee_amount' => 2400]);

        /*
         * ON SOMME LE PRODUIT ET LA TVA, et ce n'était pas mon premier réflexe.
         *
         * `708` ne porte que le HT — la TVA vit sur son propre compte. Asserter 24 € sur le seul
         * `708` mesurait donc une répartition, pas la question posée ici, qui est « la totalité de
         * ce qui a été prélevé est-elle entrée dans un livre ». Le détail de la ventilation a son
         * test, juste en dessous.
         */
        $this->assertSame(
            2400,
            $this->creditSur('708') + $this->creditSur('4457'),
            'Les frais d’annulation n’entrent dans aucun livre : on prélève de l’argent réel sur la '
            .'carte du client et le grand livre l’ignore.',
        );
    }

    /**
     * L'ÉCRITURE EST ÉQUILIBRÉE — sans quoi elle n'existerait tout simplement pas.
     *
     * `post()` lève sur un déséquilibre et `BookingAutoPoster` avale l'exception. Cette assertion
     * ne double donc pas la précédente : elle vérifie que la partie double est respectée, ce qui
     * est la seule raison pour laquelle l'écriture a pu être écrite.
     */
    public function test_lecriture_respecte_la_partie_double(): void
    {
        $reservation = $this->reservationDontLesFraisSontCaptures(fraisCents: 2400);

        $this->recevoirLeWebhook($reservation, ['application_fee_amount' => 2400]);

        $lignes = $this->lignesDeLEcriture();

        $this->assertNotEmpty($lignes, 'Aucune écriture : rien à équilibrer, le test ne mesure rien.');
        $this->assertSame(
            (int) $lignes->sum('debit_cents'),
            (int) $lignes->sum('credit_cents'),
        );
    }

    /**
     * LA TVA EST SÉPARÉE DU PRODUIT, au taux du pays de la réservation.
     *
     * 24 € TTC à 21 % : 19,83 € au produit, 4,17 € à la TVA collectée.
     */
    public function test_la_tva_est_isolee_sur_son_propre_compte(): void
    {
        $reservation = $this->reservationDontLesFraisSontCaptures(fraisCents: 2400);

        $this->recevoirLeWebhook($reservation, ['application_fee_amount' => 2400]);

        $this->assertSame(1983, $this->creditSur('708'));
        $this->assertSame(417, $this->creditSur('4457'));
    }

    /**
     * « HORS CHAMP DE LA TVA » EST UNE POSITION FISCALE, ET ZÉRO N'EST PAS UNE ABSENCE.
     *
     * Le réglage vaut `null` par défaut, ce qui signifie « taux du pays ». Poser `0` doit basculer
     * sur « hors champ » et non retomber sur le défaut — d'où le test sur `null` dans le code, et
     * non sur la vacuité. C'est exactement le piège de ce dépôt : un zéro voulu confondu avec une
     * valeur manquante.
     */
    public function test_un_taux_a_zero_est_respecte_et_non_confondu_avec_labsence(): void
    {
        config()->set('accounting_v2.marketplace.cancellation_fee_vat_rate', 0);

        $reservation = $this->reservationDontLesFraisSontCaptures(fraisCents: 2400);

        $this->recevoirLeWebhook($reservation, ['application_fee_amount' => 2400]);

        $this->assertSame(2400, $this->creditSur('708'), 'Tout le montant est un produit.');
        $this->assertSame(0, $this->creditSur('4457'), 'Aucune TVA n’est due si les frais sont hors champ.');
    }

    /**
     * LE PARTAGE SE LIT DANS LA CHARGE, IL NE SE SUPPOSE PAS.
     *
     * L'empreinte est une charge à destination. Si Stripe proratise l'`application_fee_amount` lors
     * d'une capture partielle, une part des frais file chez le prestataire — et le livre doit le
     * DIRE, au crédit de la dette `467`, plutôt que d'affirmer que la plateforme a tout gardé.
     *
     * Ici : 24 € pris, 4,80 € de commission plateforme → 19,20 € partis chez le prestataire.
     */
    public function test_la_part_reellement_transferee_devient_une_dette_prestataire(): void
    {
        $reservation = $this->reservationDontLesFraisSontCaptures(fraisCents: 2400);

        $this->recevoirLeWebhook($reservation, ['application_fee_amount' => 480]);

        $this->assertSame(1920, $this->creditSur('467'), 'Ces euros ne sont pas à nous.');
        $this->assertSame(480, $this->creditSur('708') + $this->creditSur('4457'));
    }

    /**
     * TÉMOIN INVERSE — sans champ de commission, on n'invente aucune dette.
     *
     * Sans lui, le test précédent passerait au vert sur une implémentation qui crédite `467` à tout
     * bout de champ, y compris sur les intentions sans destinataire — la variante sans prestataire
     * n'en pose aucun. Fabriquer un passif depuis un champ manquant est un défaut, pas une prudence.
     */
    public function test_temoin_sans_commission_declaree_aucune_dette_nest_inventee(): void
    {
        $reservation = $this->reservationDontLesFraisSontCaptures(fraisCents: 2400);

        $this->recevoirLeWebhook($reservation, []);

        $this->assertSame(0, $this->creditSur('467'));
        $this->assertSame(2400, $this->creditSur('708') + $this->creditSur('4457'));
    }

    /**
     * LE WEBHOOK REJOUÉ N'ÉCRIT PAS DEUX FOIS.
     *
     * Stripe redélivre. Une double écriture doublerait le produit et la TVA déclarée.
     */
    public function test_un_webhook_rejoue_ne_double_pas_lecriture(): void
    {
        $reservation = $this->reservationDontLesFraisSontCaptures(fraisCents: 2400);

        $this->recevoirLeWebhook($reservation, ['application_fee_amount' => 2400]);
        $this->recevoirLeWebhook($reservation, ['application_fee_amount' => 2400]);

        $this->assertSame(1983, $this->creditSur('708'), 'Le produit a été compté deux fois.');
        $this->assertSame(417, $this->creditSur('4457'), 'La TVA déclarée a doublé.');
    }

    /**
     * LE PIÈGE QUE CE FICHIER DOCUMENTE DÉJÀ POUR `recordEarning`.
     *
     * `capturerLesFraisDAnnulation()` pose `fee_captured` AVANT que le webhook n'arrive : le statut
     * précédent vaut donc déjà `fee_captured` quand on entre dans le gestionnaire. Une garde
     * `$previousStatus !== …`, copiée par symétrie sur celle du bloc voisin, sauterait l'écriture à
     * tous les coups.
     *
     * Ce test décrit exactement cette séquence — statut posé d'abord, webhook ensuite — et c'est
     * pour cela que tous les autres l'utilisent aussi. Il est ici sous son propre nom pour que la
     * raison ne se perde pas si quelqu'un rétablit la garde « pour faire comme au-dessus ».
     */
    public function test_lecriture_a_lieu_meme_si_le_statut_etait_deja_pose(): void
    {
        $reservation = $this->reservationDontLesFraisSontCaptures(fraisCents: 2400);

        $this->assertSame(
            MissionPaymentService::STATUT_FRAIS_CAPTURES,
            $reservation->refresh()->payment_status,
            'Garde-fou du test : si le statut n’était pas déjà posé, on ne mesurerait pas le piège.',
        );

        $this->recevoirLeWebhook($reservation, ['application_fee_amount' => 2400]);

        $this->assertGreaterThan(0, $this->creditSur('708'));
    }

    /**
     * TÉMOIN — LE CHEMIN HISTORIQUE N'A PAS BOUGÉ.
     *
     * Sans lui, tout ce qui précède passerait au vert sur une implémentation qui aurait débranché
     * l'écriture d'un encaissement normal pour la remplacer par celle des frais. Une vraie mission
     * encaissée doit toujours produire son écriture de banque.
     */
    public function test_temoin_un_encaissement_normal_ecrit_toujours_sa_ligne(): void
    {
        $scenario = SpineScenario::make()->build();

        Booking::query()->whereKey($scenario->booking->getKey())->update([
            'stripe_payment_intent_id' => 'pi_normal',
            'payment_amount_cents' => 12000,
            'payment_status' => 'captured',
            'estimated_price' => 120,
        ]);

        app(StripeWebhookHandlers::class)->handlePaymentIntentSucceeded([
            'id' => 'pi_normal',
            'amount' => 12000,
            'currency' => 'eur',
        ]);

        $this->assertGreaterThan(
            0,
            AccountingEntry::query()->where('source_type', 'Booking.payment')->count(),
            'L’encaissement ordinaire n’écrit plus rien : on a remplacé un trou par un autre.',
        );
    }

    /**
     * TÉMOIN DE PORTÉE — le réglage coupé coupe vraiment.
     *
     * `auto_post_enabled` est faux par défaut, exprès : la compta valide les écritures avant la
     * mise en ligne. Si l'ajout postait malgré le réglage, il contournerait cette décision.
     */
    public function test_le_postage_coupe_nequit_rien(): void
    {
        config()->set('accounting_v2.auto_post_enabled', false);

        $reservation = $this->reservationDontLesFraisSontCaptures(fraisCents: 2400);

        $this->recevoirLeWebhook($reservation, ['application_fee_amount' => 2400]);

        $this->assertSame(0, AccountingEntry::query()->count());
    }

    // ─────────────────────────────────────────────────────────────────────

    /**
     * L'état exact que laisse `capturerLesFraisDAnnulation()` : le statut distinct, et la trace du
     * montant réellement pris dans les métadonnées.
     */
    private function reservationDontLesFraisSontCaptures(int $fraisCents): Booking
    {
        $scenario = SpineScenario::make()->build();

        Booking::query()->whereKey($scenario->booking->getKey())->update([
            'stripe_payment_intent_id' => 'pi_empreinte',
            'payment_amount_cents' => 12000,
            'provider_amount_cents' => 9600,
            'payment_status' => MissionPaymentService::STATUT_FRAIS_CAPTURES,
            'estimated_price' => 120,
            'metadata' => ['frais_annulation' => [
                'dus_cents' => $fraisCents,
                'acompte_deja_debite_cents' => 0,
                'captures_cents' => $fraisCents,
            ]],
        ]);

        return $scenario->booking->refresh();
    }

    /** @param  array<string, mixed>  $enPlus */
    private function recevoirLeWebhook(Booking $reservation, array $enPlus): void
    {
        app(StripeWebhookHandlers::class)->handlePaymentIntentSucceeded(array_merge([
            'id' => (string) $reservation->stripe_payment_intent_id,
            'amount' => 12000,
            'amount_received' => 2400,
            'currency' => 'eur',
        ], $enPlus));
    }

    private function creditSur(string $compte): int
    {
        return (int) AccountingEntry::query()
            ->where('source_type', 'Booking.cancellation_fee')
            ->where('account_code', $compte)
            ->sum('credit_cents');
    }

    /** @return Collection<int, AccountingEntry> */
    private function lignesDeLEcriture(): Collection
    {
        return AccountingEntry::query()
            ->where('source_type', 'Booking.cancellation_fee')
            ->get();
    }
}
