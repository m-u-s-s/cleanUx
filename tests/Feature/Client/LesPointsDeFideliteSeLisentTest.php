<?php

namespace Tests\Feature\Client;

use App\Livewire\Client\LoyaltyDashboard;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LE SOLDE NE DIT NI LA TENDANCE, NI L'ORIGINE.
 *
 * L'ecran de fidelite montrait un total, un palier et une liste paginee de quinze lignes.
 * Aucun des trois ne repond aux deux questions qui decident si le client continue :
 * « est-ce que je gagne plus qu'avant ? » et « qu'est-ce qui me rapporte ? ». Les donnees
 * etaient la — a compter a la main, page par page.
 *
 * Et l'historique affichait `earn_booking`, `redeem` en chasse fixe : des identifiants de
 * base de donnees, montres tels quels a qui vient voir ses points.
 */
class LesPointsDeFideliteSeLisentTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private LoyaltyAccount $compte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->client()->create();
        $this->compte = LoyaltyAccount::factory()->create([
            'user_id' => $this->client->id,
            'lifetime_points' => 900,
            'period_points' => 900,
        ]);
    }

    private function credit(string $type, int $points, string $quand): LoyaltyTransaction
    {
        return LoyaltyTransaction::factory()->create([
            'loyalty_account_id' => $this->compte->id,
            'user_id' => $this->client->id,
            'type' => $type,
            'direction' => LoyaltyTransaction::DIRECTION_CREDIT,
            'points' => $points,
            'occurred_at' => $quand,
        ]);
    }

    public function test_les_points_du_mois_forment_une_serie_de_douze(): void
    {
        $this->credit(LoyaltyTransaction::TYPE_EARN_BOOKING, 300, now()->toDateTimeString());

        $this->actingAs($this->client);

        $serie = Livewire::test(LoyaltyDashboard::class)->instance()->pointsParMois();

        // Douze mois glissants : le palier se calcule sur cette fenetre, pas une autre.
        $this->assertCount(12, $serie);
        $this->assertSame(300, $serie[11]['points'], 'Le mois courant est le dernier de la serie.');
        $this->assertSame(0, $serie[0]['points'], 'Un mois sans point vaut zero, il ne manque pas.');
    }

    /**
     * TEMOIN — un DEBIT n'entre pas dans « points gagnes ».
     *
     * Sans ce controle, une somme qui ignorerait la direction ferait grossir la courbe a
     * chaque fois que le client DEPENSE ses points : exactement l'inverse de ce qu'elle dit.
     */
    public function test_temoin_une_depense_ne_gonfle_pas_les_gains(): void
    {
        $this->credit(LoyaltyTransaction::TYPE_EARN_BOOKING, 300, now()->toDateTimeString());

        LoyaltyTransaction::factory()->create([
            'loyalty_account_id' => $this->compte->id,
            'user_id' => $this->client->id,
            'type' => LoyaltyTransaction::TYPE_REDEEM,
            'direction' => LoyaltyTransaction::DIRECTION_DEBIT,
            'points' => 250,
            'occurred_at' => now(),
        ]);

        $this->actingAs($this->client);

        $serie = Livewire::test(LoyaltyDashboard::class)->instance()->pointsParMois();

        $this->assertSame(300, $serie[11]['points']);
    }

    public function test_l_origine_des_points_est_groupee_et_triee(): void
    {
        $this->credit(LoyaltyTransaction::TYPE_EARN_BOOKING, 200, now()->toDateTimeString());
        $this->credit(LoyaltyTransaction::TYPE_EARN_BOOKING, 100, now()->subDays(3)->toDateTimeString());
        $this->credit(LoyaltyTransaction::TYPE_EARN_REFERRAL, 150, now()->subDays(5)->toDateTimeString());

        $this->actingAs($this->client);

        $origines = Livewire::test(LoyaltyDashboard::class)->instance()->origineDesPoints();

        $this->assertCount(2, $origines, 'Deux reservations font UNE part, pas deux.');
        $this->assertSame(300, $origines[0]['points'], 'La plus grosse part vient en tete.');
        $this->assertSame('Réservation', $origines[0]['libelle']);
        $this->assertSame('Parrainage', $origines[1]['libelle']);
    }

    /**
     * TEMOIN — un type INCONNU reste visible tel quel.
     *
     * Le repli sur la valeur brute est delibere : un type que le service ajouterait doit
     * rester lisible plutot que de disparaitre derriere un libelle vide. Sans ce controle,
     * un `match` sans branche par defaut ferait lever le rendu au premier type neuf.
     */
    public function test_temoin_un_type_inconnu_reste_visible(): void
    {
        $this->assertSame('type_inedit', LoyaltyDashboard::libelleDuType('type_inedit'));
        $this->assertSame('Réservation', LoyaltyDashboard::libelleDuType('earn_booking'));
    }

    public function test_l_ecran_porte_ses_deux_graphiques_et_leur_moteur(): void
    {
        $this->credit(LoyaltyTransaction::TYPE_EARN_BOOKING, 300, now()->toDateTimeString());

        $this->actingAs($this->client);

        $rendu = Livewire::test(LoyaltyDashboard::class)->html();

        $this->assertStringContainsString('dessinerActivite', $rendu);
        $this->assertStringContainsString('dessinerRepartition', $rendu);
        $this->assertStringContainsString('data-totaux', $rendu);
        $this->assertStringContainsString('data-valeurs', $rendu);
    }

    /**
     * TEMOIN — l'historique ne montre plus ses identifiants de base.
     *
     * `earn_booking` etait affiche en chasse fixe au client. Le libelle francais le
     * remplace ; le code ne doit pas subsister a cote.
     */
    public function test_temoin_l_historique_traduit_ses_types(): void
    {
        $this->credit(LoyaltyTransaction::TYPE_EARN_BOOKING, 300, now()->toDateTimeString());

        $this->actingAs($this->client);

        $rendu = Livewire::test(LoyaltyDashboard::class)->html();

        $this->assertStringContainsString('Réservation', $rendu);
        $this->assertStringNotContainsString('earn_booking', $rendu);
    }

    /**
     * TEMOIN — sans aucun point, l'ecran se rend SANS graphique vide.
     *
     * Un anneau a zero part et des barres a plat n'apprennent rien : ils occupent la place
     * du message qui, lui, dit quoi faire.
     */
    public function test_temoin_un_compte_neuf_n_affiche_pas_de_graphique_vide(): void
    {
        $this->actingAs($this->client);

        $rendu = Livewire::test(LoyaltyDashboard::class)->assertOk()->html();

        $this->assertStringNotContainsString('dessinerRepartition', $rendu);
    }

    /**
     * TEMOIN — LE MOTEUR DE 565 Ko N'EST PAS POUSSE SANS DONNEES.
     *
     * ApexCharts est une entree Vite dediee, hors du paquet global, precisement parce qu'il
     * est lourd. Le pousser sur une page qui ne dessine pas rendrait ce choix vain — et
     * c'est le cas le plus frequent au lancement : un client sans un seul point.
     *
     * CE TEST LIT LA SOURCE, ET C'EST DELIBERE. Une requete HTTP ne peut PAS le mesurer :
     * l'environnement de test n'emet aucune balise Vite — zero, meme celles de la mise en
     * page. Une assertion « la page ne contient pas apexcharts » y passerait au vert sans
     * rien mesurer, dans les deux sens. Le chargement reel est verifie dans un navigateur
     * par `tools/visual-qa/verif-fidelite.mjs`.
     */
    public function test_temoin_le_moteur_n_est_pousse_que_s_il_sert(): void
    {
        $vue = (string) file_get_contents(
            resource_path('views/livewire/client/loyalty-dashboard.blade.php')
        );

        $this->assertMatchesRegularExpression(
            '/@if\(\$totalGagne > 0\)\s*@once\s*@push\(.scripts.\)\s*@vite/s',
            $vue,
            'La pile du moteur doit rester conditionnee au meme test que les graphiques.',
        );

        // Le meme garde tient les deux cadres : sans lui, on dessinerait des series vides.
        $this->assertStringContainsString('@if($totalGagne > 0)', $vue);
    }
}
