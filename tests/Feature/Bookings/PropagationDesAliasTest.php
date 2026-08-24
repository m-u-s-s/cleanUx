<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** LA REGLE DE PROPAGATION DES ALIAS, FIXEE PAR DES TESTS. */
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

    /** LES DEUX ECRITES ENSEMBLE : ON NE DEVINE PAS A LA PLACE DE L'APPELANT. */
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

    /** LA FRAICHEUR DECIDE : modifier UN cote deplace l'autre, meme quand les deux etaient remplis. */
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

    /** LA REGLE VAUT POUR TOUTES LES PAIRES, pas seulement celle qu'on a sous la main. */
    public function test_la_regle_vaut_pour_les_autres_paires(): void
    {
        $reservation = Booking::factory()->create([
            'frequence' => 'hebdomadaire',
            'frequency' => null,
            'telephone_client' => '+32470112233',
            'contact_phone' => null,
        ]);

        $frais = $reservation->fresh();

        // `type_lieu` a quitté la liste : la paire est effondrée, seul `place_type` subsiste.
        $this->assertSame('hebdomadaire', $frais->frequency);
        $this->assertSame('+32470112233', $frais->contact_phone);
    }
}
