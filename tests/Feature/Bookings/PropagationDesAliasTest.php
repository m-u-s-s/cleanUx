<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LA REGLE DE PROPAGATION DES ALIAS, FIXEE PAR DES TESTS.
 *
 * Le test portait sur `commentaire_client / customer_comment`, une paire depuis EFFONDREE.
 * Il porte maintenant sur `adresse / address`, qui vit encore — et le restera tant que la
 * paire n'aura pas ete arbitree a son tour.
 */
class PropagationDesAliasTest extends TestCase
{
    use RefreshDatabase;

    public function test_temoin_le_cote_francais_seul_remplit_l_anglais(): void
    {
        $reservation = Booking::factory()->create([
            'adresse' => 'Rue du Portail 12',
            'address' => null,
        ]);

        $this->assertSame('Rue du Portail 12', $reservation->fresh()->address);
    }

    public function test_le_cote_anglais_seul_remplit_le_francais(): void
    {
        $reservation = Booking::factory()->create([
            'adresse' => null,
            'address' => 'Gate Street 12',
        ]);

        $this->assertSame('Gate Street 12', $reservation->fresh()->adresse);
    }

    /** Ecrire les DEUX cotes d'un coup : chacun garde ce qu'on lui a donne. */
    public function test_les_deux_ecrites_ensemble_ne_s_ecrasent_pas(): void
    {
        $reservation = Booking::factory()->create([
            'adresse' => 'Rue du Portail 12',
            'address' => 'Gate Street 12',
        ]);

        $frais = $reservation->fresh();

        $this->assertSame('Rue du Portail 12', $frais->adresse);
        $this->assertSame('Gate Street 12', $frais->address);
    }

    /** COMBLER UN TROU NE SUFFIT PAS : un changement doit se PROPAGER. */
    public function test_modifier_un_seul_cote_deplace_l_autre(): void
    {
        $reservation = Booking::factory()->create([
            'adresse' => 'Rue du Portail 12',
            'address' => 'Gate Street 12',
        ]);

        $reservation->update(['adresse' => 'Avenue Neuve 3']);

        $this->assertSame(
            'Avenue Neuve 3',
            $reservation->fresh()->address,
            'Le cote anglais est reste sur l’ancienne adresse.',
        );
    }

    /** Ne toucher a aucun des deux ne doit RIEN deplacer. */
    public function test_ne_toucher_a_aucun_des_deux_ne_change_rien(): void
    {
        $reservation = Booking::factory()->create([
            'adresse' => 'Rue du Portail 12',
            'address' => 'Gate Street 12',
        ]);

        $reservation->update(['motif' => 'Autre chose']);

        $frais = $reservation->fresh();

        $this->assertSame('Rue du Portail 12', $frais->adresse);
        $this->assertSame('Gate Street 12', $frais->address);
    }

    /** LA REGLE VAUT POUR TOUTES LES PAIRES, pas seulement celle qu'on a sous la main. */
    public function test_la_regle_vaut_pour_les_autres_paires(): void
    {
        // La fabrique remplit DEJA les deux cotes : passer `null` a `create()` ne creuse aucun
        // trou. On le creuse apres coup, puis on laisse le trait faire son travail au `save()`.
        $reservation = Booking::factory()->create();

        $reservation->forceFill([
            'ville' => 'Bruxelles',
            'city' => null,
            'code_postal' => '1000',
            'postal_code' => null,
        ])->save();

        $frais = $reservation->fresh();

        $this->assertSame('Bruxelles', $frais->city);
        $this->assertSame('1000', $frais->postal_code);
    }
}
