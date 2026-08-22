<?php

namespace Tests\Feature\Ai;

use App\Models\Sector;
use App\Models\Trade;
use App\Services\Ai\OrderIntentInterpreter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LE REPLI PAR MOTS-CLES NE CONNAISSAIT QUE LE FRANCAIS.
 *
 * Quand le modele n'est pas joignable — cle absente, panne, delai depasse — l'assistant retombe
 * sur `interpreterParMotsCles()` : il decoupe la phrase du client et compte les mots presents dans
 * le libelle et la description de chaque metier.
 *
 * Ce corpus etait construit sur le seul libelle FRANCAIS. Un client neerlandophone ecrivant
 * « schilderwerk » obtenait donc zero sur chaque metier, et lisait « Nous n'avons pas reconnu le
 * service » — alors que le service existe et porte ce nom depuis que le catalogue se traduit.
 *
 * Le premier test est le TEMOIN : sans lui, « le neerlandais est reconnu » passerait au vert meme
 * si l'interpreteur etait casse pour une tout autre raison.
 */
class RepliMotsClesMultilingueTest extends TestCase
{
    use RefreshDatabase;

    private function peinture(): Trade
    {
        // `Sector` n'a pas de fabrique dans ce depot : on le cree directement.
        $secteur = Sector::create(['name' => 'Batiment', 'slug' => 'batiment-test', 'is_active' => true, 'sort_order' => 1]);

        return Trade::factory()->create([
            'name' => 'Peinture interieure',
            'slug' => 'peinture-test',
            'description' => 'Murs et plafonds',
            'sector_id' => $secteur->id,
            'is_active' => true,
        ]);
    }

    public function test_temoin_une_phrase_francaise_reconnait_le_metier(): void
    {
        $metier = $this->peinture();

        $resultat = (new OrderIntentInterpreter)->interpreter('je voudrais faire de la peinture dans le salon');

        $this->assertSame($metier->id, $resultat['trade_id']);
    }

    public function test_une_phrase_neerlandaise_reconnait_le_meme_metier(): void
    {
        $metier = $this->peinture();
        $metier->setTranslation('name', 'nl', 'Schilderwerk');

        $resultat = (new OrderIntentInterpreter)->interpreter('ik wil schilderwerk in de woonkamer');

        $this->assertSame(
            $metier->id,
            $resultat['trade_id'],
            'Le repli compte des mots : sans les traductions dans son corpus, il ne voit que le francais.',
        );
    }

    /** Un mot d'une TROISIEME langue marche aussi : le corpus les porte toutes. */
    public function test_un_mot_anglais_reconnait_le_metier(): void
    {
        $metier = $this->peinture();
        $metier->setTranslation('name', 'en', 'Painting');

        $resultat = (new OrderIntentInterpreter)->interpreter('I need painting for my living room');

        $this->assertSame($metier->id, $resultat['trade_id']);
    }
}
