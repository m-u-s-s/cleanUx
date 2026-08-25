<?php

namespace Tests\Feature\Api\Client;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LES CLES QUE L'ECRAN DE PARRAINAGE NATIF LIT.
 *
 * Six d'entre elles etaient inventees : l'ecran declarait `total_referrals`, `min_referrals`,
 * un bloc `stats` a plat, un `code` et un `message`. La charge utile n'en porte aucune. Les
 * deux compteurs restaient a zero, le palier ne s'affichait jamais, la ligne de progression
 * etait morte, le partage n'ouvrait rien et le code ne se copiait pas — tout l'ecran sauf
 * son titre. Une fixture ecrite a la main tenait la suite native au vert.
 *
 * Ce test vit cote serveur : c'est le seul endroit ou la forme ne peut pas etre inventee.
 */
class ContratDuParrainageNatifTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_statistiques_portent_les_cles_que_l_ecran_lit(): void
    {
        $client = User::factory()->client()->create();

        $reponse = $this->actingAs($client, 'sanctum')
            ->getJson('/api/client/referral/stats')
            ->assertOk();

        $reponse->assertJsonStructure([
            'data' => [
                'referral_code',
                'invite_url',
                'rewards' => ['referrer_amount', 'referee_amount', 'currency'],
                'stats' => ['total_invited', 'total_qualified', 'total_earned'],
            ],
        ]);

        // Les cles inventees ne doivent PAS reapparaitre : leur retour ferait taire l'ecran.
        $this->assertArrayNotHasKey('total_referrals', $reponse->json('data'));
        $this->assertArrayNotHasKey('total_earned', $reponse->json('data'));
    }

    public function test_le_palier_suivant_porte_son_seuil(): void
    {
        $client = User::factory()->client()->create();

        $palier = $this->actingAs($client, 'sanctum')
            ->getJson('/api/client/referral/stats')
            ->assertOk()
            ->json('data.next_tier');

        // Un compte neuf n'a franchi aucun palier : le suivant existe forcement.
        $this->assertIsArray($palier);
        $this->assertArrayHasKey('threshold', $palier);
        $this->assertArrayHasKey('name', $palier);
        $this->assertArrayNotHasKey('min_referrals', $palier);
    }

    public function test_le_code_de_partage_porte_les_cles_que_l_ecran_lit(): void
    {
        $client = User::factory()->client()->create();

        $donnees = $this->actingAs($client, 'sanctum')
            ->getJson('/api/client/referral/my-code')
            ->assertOk()
            ->json('data');

        $this->assertArrayHasKey('referral_code', $donnees);
        $this->assertArrayHasKey('share_message', $donnees);
        $this->assertArrayNotHasKey('code', $donnees);
        $this->assertArrayNotHasKey('message', $donnees);
    }

    /**
     * TEMOIN — les montants de recompense sont des DONNEES, pas des constantes deguisees.
     *
     * L'ecran promettait « 15 » et « 10 » en dur. Sans ce controle, un serveur qui renverrait
     * lui aussi des constantes laisserait le defaut intact : il faut que la configuration
     * atteigne bien la charge utile.
     */
    public function test_temoin_les_recompenses_suivent_la_configuration(): void
    {
        config(['referral.rewards.referrer.amount' => 4200, 'referral.rewards.referee.amount' => 3300]);

        $client = User::factory()->client()->create();

        $recompenses = $this->actingAs($client, 'sanctum')
            ->getJson('/api/client/referral/stats')
            ->assertOk()
            ->json('data.rewards');

        $this->assertSame(42.0, (float) $recompenses['referrer_amount']);
        $this->assertSame(33.0, (float) $recompenses['referee_amount']);
    }
}
