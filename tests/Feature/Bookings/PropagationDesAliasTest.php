<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LA REGLE DE PROPAGATION DES ALIAS, FIXEE PAR DES TESTS.
 *
 * `bookings` porte quinze paires de colonnes jumelles, heritees de la fusion FR/EN.
 * `HasLegacyBookingAliases::propagerLaPaire()` les tient d'accord, et sa regle n'est pas
 * « recopier » mais SUIVRE LA FRAICHEUR :
 *
 *   un trou se comble toujours ;
 *   si UNE SEULE des deux a change, c'est elle qui fait foi ;
 *   si les DEUX ont change, l'appelant a tranche lui-meme et on ne devine pas a sa place.
 *
 * La troisieme branche n'etait couverte par aucun test. Elle decide pourtant du comportement quand
 * un appelant ecrit les deux cotes — et c'est elle que touche l'optimisation du releve de colonnes
 * modifiees (un seul `getDirty()` par enregistrement au lieu de trente).
 *
 * ── POURQUOI `commentaire_client` ET NON `ville` ──────────────────────────────────────────────
 *
 * Une premiere version de ce fichier employait la paire `ville`/`city`. Les quatre tests
 * echouaient — y compris le temoin — et l'optimisation n'y etait pour RIEN : retiree, ils
 * echouaient a l'identique.
 *
 * La cause est dans la fabrique. `BookingFactory::synchronizeStructuredContext()`, appelee par
 * `afterMaking` ET `afterCreating`, fait `$rendezVous->ville = $postalCode->city_name;` — une
 * affectation INCONDITIONNELLE, pas un `??=`. Elle ecrase donc `ville` et `code_postal` APRES les
 * surcharges passees a `create()`. Un test bati dessus ne mesure pas le trait, il mesure la
 * fabrique.
 *
 * `commentaire_client`/`customer_comment` echappe a ce crochet : la fabrique pose le cote francais
 * dans sa definition — donc surchargeable — et ne touche jamais l'anglais.
 */
class PropagationDesAliasTest extends TestCase
{
    use RefreshDatabase;

    public function test_temoin_le_cote_francais_seul_remplit_l_anglais(): void
    {
        $reservation = Booking::factory()->create([
            'commentaire_client' => 'Portail au fond de la cour.',
            'customer_comment' => null,
        ]);

        $this->assertSame('Portail au fond de la cour.', $reservation->fresh()->customer_comment);
    }

    public function test_le_cote_anglais_seul_remplit_le_francais(): void
    {
        $reservation = Booking::factory()->create([
            'commentaire_client' => null,
            'customer_comment' => 'Ring twice.',
        ]);

        $this->assertSame('Ring twice.', $reservation->fresh()->commentaire_client);
    }

    /**
     * LES DEUX ECRITES ENSEMBLE : ON NE DEVINE PAS A LA PLACE DE L'APPELANT.
     *
     * C'est la seule branche ou le trait se TAIT. La confondre avec les autres ferait ecraser une
     * valeur que quelqu'un a deliberement posee.
     */
    public function test_les_deux_ecrites_ensemble_ne_s_ecrasent_pas(): void
    {
        $reservation = Booking::factory()->create([
            'commentaire_client' => 'Sonner deux fois.',
            'customer_comment' => 'Sonner deux fois.',
        ]);

        $reservation->update([
            'commentaire_client' => 'Code portail 1234.',
            'customer_comment' => 'Gate code 1234.',
        ]);

        $frais = $reservation->fresh();
        $this->assertSame('Code portail 1234.', $frais->commentaire_client);
        $this->assertSame('Gate code 1234.', $frais->customer_comment);
    }

    /**
     * LA FRAICHEUR DECIDE : modifier UN cote deplace l'autre, meme quand les deux etaient remplis.
     *
     * C'est le defaut d'origine que ce trait a corrige : la version qui ne comblait que les trous
     * laissait la reprogrammation ecrire `scheduled_date` sans bouger `date`, et le moteur
     * d'annulation facturait contre le creneau abandonne.
     */
    public function test_modifier_un_seul_cote_deplace_l_autre(): void
    {
        $reservation = Booking::factory()->create([
            'commentaire_client' => 'Sonner deux fois.',
            'customer_comment' => 'Sonner deux fois.',
        ]);

        $reservation->update(['customer_comment' => 'Ring once.']);

        $this->assertSame(
            'Ring once.',
            $reservation->fresh()->commentaire_client,
            'Le cote modifie fait foi : l’autre le suit.',
        );
    }

    public function test_ne_toucher_a_aucun_des_deux_ne_change_rien(): void
    {
        $reservation = Booking::factory()->create([
            'commentaire_client' => 'Sonner deux fois.',
            'customer_comment' => 'Sonner deux fois.',
        ]);

        // Une colonne assignable en masse, et hors de toute paire d'alias.
        $reservation->update(['status' => 'confirme']);

        $frais = $reservation->fresh();
        $this->assertSame('Sonner deux fois.', $frais->commentaire_client);
        $this->assertSame('Sonner deux fois.', $frais->customer_comment);
    }

    /**
     * LA REGLE VAUT POUR TOUTES LES PAIRES, pas seulement celle qu'on a sous la main.
     *
     * Trois paires que la fabrique ne force pas, verifiees d'un coup : sans cela, une paire
     * ajoutee demain a `$legacyAliasPairs` pourrait ne jamais etre exercee.
     */
    public function test_la_regle_vaut_pour_les_autres_paires(): void
    {
        $reservation = Booking::factory()->create([
            'type_lieu' => 'appartement',
            'place_type' => null,
            'frequence' => 'hebdomadaire',
            'frequency' => null,
            'telephone_client' => '+32470112233',
            'contact_phone' => null,
        ]);

        $frais = $reservation->fresh();

        $this->assertSame('appartement', $frais->place_type);
        $this->assertSame('hebdomadaire', $frais->frequency);
        $this->assertSame('+32470112233', $frais->contact_phone);
    }
}
