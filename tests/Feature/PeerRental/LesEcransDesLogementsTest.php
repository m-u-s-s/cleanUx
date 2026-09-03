<?php

namespace Tests\Feature\PeerRental;

use App\Livewire\PeerRental\PeerMyStays;
use App\Livewire\PeerRental\PeerStayEditor;
use App\Livewire\PeerRental\PeerStayPage;
use App\Models\PeerStay;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LES ECRANS DU LOGEMENT — PROPRIETAIRE ET VOYAGEUR.
 *
 * Une annonce nait en BROUILLON, se complete, puis part EN VERIFICATION : le proprietaire ne
 * publie jamais lui-meme. Cote voyageur, le total reel s'affiche avant toute saisie de paiement.
 */
class LesEcransDesLogementsTest extends TestCase
{
    use RefreshDatabase;

    /** UNE ANNONCE NEUVE NAIT EN BROUILLON : elle n'est visible de personne. */
    public function test_une_annonce_neuve_nait_en_brouillon(): void
    {
        $membre = User::factory()->create();

        Livewire::actingAs($membre)->test(PeerMyStays::class)->call('creer');

        $logement = PeerStay::query()->where('owner_id', $membre->id)->firstOrFail();

        $this->assertSame(PeerStay::STATUT_BROUILLON, $logement->status);
        $this->assertNull($logement->published_at);
    }

    /**
     * L'ANNONCE APPARTIENT A SON PROPRIETAIRE.
     *
     * L'identifiant vient de l'URL : sans cette garde, changer un chiffre dans la barre d'adresse
     * ouvrirait l'annonce d'un autre membre — avec son adresse et ses prix.
     */
    public function test_un_autre_membre_ne_peut_pas_ouvrir_l_editeur(): void
    {
        $logement = PeerStay::factory()->create();

        Livewire::actingAs(User::factory()->create())
            ->test(PeerStayEditor::class, ['stay' => $logement])
            ->assertForbidden();
    }

    /** TEMOIN — le proprietaire, lui, entre bien dans son propre editeur. */
    public function test_temoin_le_proprietaire_ouvre_son_editeur(): void
    {
        $logement = PeerStay::factory()->create();

        Livewire::actingAs($logement->owner)
            ->test(PeerStayEditor::class, ['stay' => $logement])
            ->assertOk();
    }

    /** CE QUI MANQUE POUR PUBLIER EST DIT A L'AVANCE, pas decouvert apres un refus. */
    public function test_l_editeur_nomme_ce_qui_manque_pour_publier(): void
    {
        $logement = PeerStay::factory()->create(['title' => '', 'description' => null, 'city' => null]);

        $motifs = Livewire::actingAs($logement->owner)
            ->test(PeerStayEditor::class, ['stay' => $logement])
            ->get('motifsDeBlocage');

        $this->assertNotEmpty($motifs);
        $this->assertTrue(collect($motifs)->contains(fn (string $m) => str_contains($m, 'titre')));
        $this->assertTrue(collect($motifs)->contains(fn (string $m) => str_contains($m, 'photo')));
    }

    /** UNE ANNONCE INCOMPLETE NE PART PAS EN VERIFICATION. */
    public function test_une_annonce_incomplete_ne_part_pas_en_verification(): void
    {
        $logement = PeerStay::factory()->create(['title' => '']);

        Livewire::actingAs($logement->owner)
            ->test(PeerStayEditor::class, ['stay' => $logement])
            ->call('demanderLaPublication');

        $this->assertSame(PeerStay::STATUT_BROUILLON, $logement->fresh()->status);
    }

    /**
     * UN PLAFOND SOUS LE PLANCHER REND TOUTE RESERVATION IMPOSSIBLE.
     *
     * Aucun message ne l'expliquerait au voyageur : la saisie se corrige donc a l'enregistrement.
     */
    public function test_un_plafond_de_nuits_sous_le_plancher_est_corrige(): void
    {
        $logement = PeerStay::factory()->create();

        Livewire::actingAs($logement->owner)
            ->test(PeerStayEditor::class, ['stay' => $logement])
            ->set('titre', 'Studio')
            ->set('nuitsMin', 7)
            ->set('nuitsMax', 3)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertSame(7, (int) $logement->fresh()->max_nights);
    }

    /** LES VOYAGEURS INCLUS NE DEPASSENT PAS LA CAPACITE : sinon le supplement ne s'applique jamais. */
    public function test_les_voyageurs_inclus_ne_depassent_pas_la_capacite(): void
    {
        $logement = PeerStay::factory()->create();

        Livewire::actingAs($logement->owner)
            ->test(PeerStayEditor::class, ['stay' => $logement])
            ->set('titre', 'Studio')
            ->set('voyageursMax', 2)
            ->set('voyageursInclus', 6)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertSame(2, (int) $logement->fresh()->guests_included);
    }

    /** UN EQUIPEMENT INCONNU N'ENTRE PAS DANS L'ANNONCE. */
    public function test_un_equipement_inconnu_est_ecarte(): void
    {
        $logement = PeerStay::factory()->create();

        Livewire::actingAs($logement->owner)
            ->test(PeerStayEditor::class, ['stay' => $logement])
            ->set('titre', 'Studio')
            ->set('equipements', ['wifi', 'heliport-prive'])
            ->call('enregistrer');

        $this->assertSame(['wifi'], $logement->fresh()->equipements());
    }

    /** UNE PERIODE FERMEE SE POSE ET SE ROUVRE depuis l'editeur. */
    public function test_le_proprietaire_ferme_puis_rouvre_une_periode(): void
    {
        $logement = PeerStay::factory()->create();

        $composant = Livewire::actingAs($logement->owner)
            ->test(PeerStayEditor::class, ['stay' => $logement])
            ->set('fermetureDebut', now()->addDays(5)->toDateString())
            ->set('fermetureFin', now()->addDays(9)->toDateString())
            ->call('fermerUnePeriode');

        $this->assertSame(1, $logement->indisponibilites()->count());

        $composant->call('rouvrirUnePeriode', (int) $logement->indisponibilites()->first()->id);

        $this->assertSame(0, $logement->fresh()->indisponibilites()->count());
    }

    /**
     * UNE ANNONCE NON PUBLIEE N'A PAS D'URL PUBLIQUE.
     *
     * La laisser lisible exposerait l'adresse d'un logement que son proprietaire n'a pas mis en
     * ligne — c'est l'information la plus sensible d'une annonce.
     */
    public function test_une_annonce_non_publiee_est_invisible_des_visiteurs(): void
    {
        $logement = PeerStay::factory()->create();

        Livewire::actingAs(User::factory()->create())
            ->test(PeerStayPage::class, ['stay' => $logement])
            ->assertNotFound();
    }

    /** TEMOIN — la meme annonce, publiee, s'ouvre bien. */
    public function test_temoin_une_annonce_publiee_s_ouvre(): void
    {
        $logement = PeerStay::factory()->publiee()->create();

        Livewire::actingAs(User::factory()->create())
            ->test(PeerStayPage::class, ['stay' => $logement])
            ->assertOk()
            ->assertSee($logement->title);
    }

    /** LE PROPRIETAIRE VOIT SON BROUILLON, lui : c'est ainsi qu'il le relit avant publication. */
    public function test_le_proprietaire_voit_son_propre_brouillon(): void
    {
        $logement = PeerStay::factory()->create();

        Livewire::actingAs($logement->owner)
            ->test(PeerStayPage::class, ['stay' => $logement])
            ->assertOk();
    }

    /** LE TOTAL REEL S'AFFICHE AVANT toute saisie de moyen de paiement. */
    public function test_le_devis_apparait_des_que_les_dates_sont_choisies(): void
    {
        $logement = PeerStay::factory()->publiee()->create([
            'nightly_price_cents' => 10000,
            'cleaning_fee_cents' => 3000,
            'min_nights' => 1,
        ]);

        $devis = Livewire::actingAs(User::factory()->create())
            ->test(PeerStayPage::class, ['stay' => $logement])
            ->set('debut', now()->addDays(10)->toDateString())
            ->set('fin', now()->addDays(12)->toDateString())
            ->get('devis');

        $this->assertNotNull($devis);
        $this->assertSame(2, $devis['days']);
        $this->assertSame(3000, $devis['supplements']['menage']);
    }

    /**
     * ON NE LOUE PAS CHEZ SOI.
     *
     * Sans cette garde, un proprietaire bloquerait son propre calendrier par une reservation et
     * fausserait sa disponibilite.
     */
    public function test_un_proprietaire_ne_reserve_pas_son_propre_logement(): void
    {
        $logement = PeerStay::factory()->publiee()->create(['min_nights' => 1]);

        Livewire::actingAs($logement->owner)
            ->test(PeerStayPage::class, ['stay' => $logement])
            ->set('debut', now()->addDays(10)->toDateString())
            ->set('fin', now()->addDays(12)->toDateString())
            ->set('paymentMethodId', 'pm_test')
            ->call('reserver')
            ->assertSet('erreur', 'Vous ne pouvez pas réserver votre propre logement.');

        $this->assertSame(0, $logement->rentals()->count());
    }

    /**
     * LE PRIX DE SAISON SE REGLE, ET S ECRIT.
     *
     * `pricing_rules` etait lue par le calcul du devis et ecrite par AUCUN ecran : les valeurs de
     * `config/peer_rental.pricing` majoraient chaque annonce sans que son hote puisse les voir,
     * encore moins les changer. C est la signature « colonne lue, jamais ecrite ».
     */
    public function test_le_proprietaire_regle_son_prix_de_saison(): void
    {
        $logement = PeerStay::factory()->create();

        Livewire::actingAs($logement->owner)
            ->test(PeerStayEditor::class, ['stay' => $logement])
            ->set('titre', 'Loft du canal')
            ->set('majorationWeekend', 30)
            ->set('majorationHauteSaison', 60)
            ->set('moisHauteSaison', [7, 8])
            ->call('enregistrer')
            ->assertHasNoErrors();

        $regles = $logement->fresh()->reglesDePrix();

        $this->assertEqualsWithDelta(1.3, $regles['weekend_multiplier'], 0.001);
        $this->assertEqualsWithDelta(1.6, $regles['high_season_multiplier'], 0.001);
        $this->assertSame([7, 8], $regles['high_season_months']);
    }

    /** LE DEVIS SUIT CE REGLAGE : sans quoi l ecran n aurait rien regle du tout. */
    public function test_la_majoration_de_week_end_se_retrouve_dans_le_devis(): void
    {
        $logement = PeerStay::factory()->publiee()->create([
            'nightly_price_cents' => 10000,
            'cleaning_fee_cents' => 0,
            'min_nights' => 1,
            'pricing_rules' => [
                'weekend_multiplier' => 2.0,
                'high_season_multiplier' => 1,
                'high_season_months' => [],
            ],
        ]);

        // Un samedi, une seule nuit : la majoration se lit sans se melanger aux autres.
        $samedi = now()->addWeek()->next(CarbonInterface::SATURDAY);

        $devis = Livewire::actingAs(User::factory()->create())
            ->test(PeerStayPage::class, ['stay' => $logement])
            ->set('debut', $samedi->toDateString())
            ->set('fin', $samedi->copy()->addDay()->toDateString())
            ->get('devis');

        $this->assertNotNull($devis);
        $this->assertSame(20000, $devis['subtotal_cents']);
    }

    /** TEMOIN — sans majoration, la meme nuit de samedi reste au prix affiche. */
    public function test_temoin_sans_majoration_le_samedi_reste_au_prix_affiche(): void
    {
        $logement = PeerStay::factory()->publiee()->create([
            'nightly_price_cents' => 10000,
            'cleaning_fee_cents' => 0,
            'min_nights' => 1,
            'pricing_rules' => [
                'weekend_multiplier' => 1,
                'high_season_multiplier' => 1,
                'high_season_months' => [],
            ],
        ]);

        $samedi = now()->addWeek()->next(CarbonInterface::SATURDAY);

        $devis = Livewire::actingAs(User::factory()->create())
            ->test(PeerStayPage::class, ['stay' => $logement])
            ->set('debut', $samedi->toDateString())
            ->set('fin', $samedi->copy()->addDay()->toDateString())
            ->get('devis');

        $this->assertSame(10000, $devis['subtotal_cents']);
    }

    /**
     * UN BAREME D ANNULATION HORS LISTE EST REFUSE.
     *
     * `fraisDAnnulation()` ne connait que trois cles ; toute autre ne trouve aucun palier.
     */
    public function test_une_politique_d_annulation_inconnue_est_refusee(): void
    {
        $logement = PeerStay::factory()->create();

        Livewire::actingAs($logement->owner)
            ->test(PeerStayEditor::class, ['stay' => $logement])
            ->set('titre', 'Loft du canal')
            ->set('politiqueDAnnulation', 'flexible')
            ->call('enregistrer')
            ->assertHasErrors('politiqueDAnnulation');
    }

    /** TEMOIN — les trois cles du bareme passent bien. */
    public function test_temoin_les_trois_baremes_connus_passent(): void
    {
        $logement = PeerStay::factory()->create();

        foreach (['souple', 'moderee', 'stricte'] as $politique) {
            Livewire::actingAs($logement->owner)
                ->test(PeerStayEditor::class, ['stay' => $logement])
                ->set('titre', 'Loft du canal')
                ->set('politiqueDAnnulation', $politique)
                ->call('enregistrer')
                ->assertHasNoErrors();

            $this->assertSame($politique, $logement->fresh()->cancellation_policy, $politique);
        }
    }
}
