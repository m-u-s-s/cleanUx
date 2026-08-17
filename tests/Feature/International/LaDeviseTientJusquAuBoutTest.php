<?php

namespace Tests\Feature\International;

use App\Models\Booking;
use App\Models\Country;
use App\Models\ProviderWalletTransaction;
use App\Models\User;
use App\Services\Country\CountryConfigService;
use App\Services\Payments\ProviderWalletService;
use App\Support\International\Devise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LA DEVISE NE DOIT PAS SE PERDRE ENTRE LA COMMANDE ET LE VERSEMENT.
 *
 * Le parcours de commande a été ramené sur une autorité unique, tirée de la position. Tout ce qui
 * vient APRÈS — pourboire, devis, facture, forfait, portefeuille, transfert Stripe — recopiait
 * `'EUR'`. Une commande marocaine partait donc juste et devenait fausse à la ligne suivante, sans
 * qu'aucune erreur ne soit levée : quinze littéraux et une trentaine de replis `?? 'EUR'`, chacun
 * parfaitement raisonnable isolément.
 *
 * ── LES DEUX QUI FAISAIENT LE PLUS DE MAL ────────────────────────────────────────────────────
 *
 * LE PORTEFEUILLE. `balance()` prenait `string $currency = 'EUR'` et filtrait dessus. Pour un
 * prestataire payé en dirhams, la requête ne trouvait AUCUNE ligne : son portefeuille affichait
 * zéro. Pas d'erreur, pas de trace — un professionnel à qui l'on montre qu'on ne lui doit rien.
 *
 * LE TRANSFERT STRIPE. `ProcessProviderPayouts` envoyait `'currency' => 'eur'` sur un encaissement
 * qui pouvait être en dirhams. C'est le seul endroit de ce lot où de l'argent BOUGE réellement.
 *
 * ── ET LA LISTE PUBLIQUE DES PAYS ────────────────────────────────────────────────────────────
 *
 * `/api/countries` servait une constante de neuf pays européens. Créer le Maroc dans `/admin`
 * ouvrait bien les zones et posait bien MAD — et le pays n'apparaissait nulle part côté client. Le
 * catalogue géographique et cette liste décrivaient deux mondes.
 */
class LaDeviseTientJusquAuBoutTest extends TestCase
{
    use RefreshDatabase;

    // ── Le portefeuille ──────────────────────────────────────────────────

    public function test_un_prestataire_paye_en_dirhams_voit_son_solde(): void
    {
        $prestataire = User::factory()->employe()->create();

        $this->crediter($prestataire, 250.0, 'MAD');

        $solde = app(ProviderWalletService::class)->balance($prestataire->id);

        $this->assertSame('MAD', $solde['currency']);
        $this->assertSame(
            250.0,
            $solde['available'],
            'Le solde est filtré sur une devise que ce prestataire n’utilise pas : son '
            .'portefeuille s’affiche vide alors qu’on lui doit de l’argent.',
        );
    }

    /**
     * TÉMOIN — un prestataire en euros voit toujours le sien.
     *
     * Sans lui, le test précédent serait vert sur une implémentation qui aurait simplement retiré
     * le filtre de devise : les soldes de deux monnaies s'additionneraient, ce qui est un faux.
     */
    public function test_temoin_un_prestataire_en_euros_voit_toujours_le_sien(): void
    {
        $prestataire = User::factory()->employe()->create();

        $this->crediter($prestataire, 80.0, 'EUR');

        $solde = app(ProviderWalletService::class)->balance($prestataire->id);

        $this->assertSame('EUR', $solde['currency']);
        $this->assertSame(80.0, $solde['available']);
    }

    /**
     * ON N'ADDITIONNE PAS DEUX MONNAIES.
     *
     * Un total unique mélangeant euros et dirhams serait un nombre qui ne veut rien dire. Le solde
     * rendu est celui de la monnaie la plus récente, et la clé `currency` le dit.
     */
    public function test_deux_monnaies_ne_sadditionnent_pas(): void
    {
        $prestataire = User::factory()->employe()->create();

        $this->crediter($prestataire, 80.0, 'EUR');
        $this->crediter($prestataire, 250.0, 'MAD');

        $solde = app(ProviderWalletService::class)->balance($prestataire->id);

        $this->assertSame('MAD', $solde['currency']);
        $this->assertSame(250.0, $solde['available'], 'Les deux monnaies ont été mélangées.');
    }

    /** Et la devise demandée explicitement est toujours respectée. */
    public function test_une_devise_demandee_explicitement_est_respectee(): void
    {
        $prestataire = User::factory()->employe()->create();

        $this->crediter($prestataire, 80.0, 'EUR');
        $this->crediter($prestataire, 250.0, 'MAD');

        $solde = app(ProviderWalletService::class)->balance($prestataire->id, 'EUR');

        $this->assertSame('EUR', $solde['currency']);
        $this->assertSame(80.0, $solde['available']);
    }

    /** Un portefeuille vide retombe sur la devise de la plateforme, sans lever. */
    public function test_un_portefeuille_vide_repond_la_devise_de_la_plateforme(): void
    {
        $prestataire = User::factory()->employe()->create();

        $this->assertSame(
            Devise::plateforme(),
            app(ProviderWalletService::class)->balance($prestataire->id)['currency'],
        );
    }

    // ── Les objets d'aval ────────────────────────────────────────────────

    public function test_un_pourboire_suit_la_monnaie_de_sa_mission(): void
    {
        $reservation = Booking::factory()->create(['currency' => 'MAD']);

        $source = (string) file_get_contents(base_path('app/Services/Tips/TipService.php'));

        $this->assertStringContainsString('Devise::premiereRenseignee($booking->currency)', $source);
        $this->assertSame(
            0,
            preg_match("/'currency'\s*=>\s*'[A-Za-z]{3}'/", $source),
            'Le pourboire fixe encore une devise en dur.',
        );

        // Garde-fou : la réservation porte bien la devise qu'on croit.
        $this->assertSame('MAD', $reservation->refresh()->currency);
    }

    /**
     * AUCUN OBJET D'ARGENT NE FIXE PLUS DE DEVISE EN DUR.
     *
     * On relit les sources parce que c'est la seule façon de couvrir d'un coup les huit écrivains
     * remontés — et parce que le neuvième naîtra de la même manière que les huit premiers : une
     * ligne raisonnable, écrite seule, dans un fichier qu'on ne relit pas.
     */
    public function test_aucun_ecrivain_dargent_ne_fixe_de_devise(): void
    {
        $ecrivains = [
            'app/Services/Tips/TipService.php',
            'app/Services/Finance/Concerns/SynchronizesFinanceDocuments.php',
            'app/Services/Bundles/MultiTradeBundleService.php',
            'app/Console/Commands/ProcessProviderPayouts.php',
            'app/Http/Controllers/Api/Provider/ProviderPayoutsController.php',
            'app/Http/Controllers/Api/Provider/ProviderWalletController.php',
            'app/Services/Payments/ProviderWalletService.php',
            'app/Livewire/Admin/B2BOperationsCenter.php',
        ];

        $coupables = [];

        foreach ($ecrivains as $chemin) {
            $source = (string) file_get_contents(base_path($chemin));

            $this->assertNotSame('', $source, "{$chemin} est introuvable : le test ne mesure plus rien.");

            // On cherche l'AFFECTATION d'un littéral, dans les deux casses — Stripe attend des
            // minuscules, et c'est sous cette forme que le transfert se trompait.
            if (preg_match("/'currency'\s*=>\s*'[A-Za-z]{3}'/", $source) === 1) {
                $coupables[] = "{$chemin} affecte une devise littérale";
            }

            // Et le repli `= 'EUR'` d'un paramètre, qui est ce qui vidait le portefeuille.
            if (preg_match("/\\\$currency\s*=\s*'[A-Za-z]{3}'/", $source) === 1) {
                $coupables[] = "{$chemin} impose une devise par défaut";
            }
        }

        $this->assertSame([], $coupables, implode("\n", $coupables));
    }

    /**
     * TÉMOIN DU MOTIF — il reconnaît les deux formes fautives, et épargne les innocentes.
     *
     * Sans lui, le test précédent serait vert sur une expression qui ne mord jamais.
     */
    public function test_temoin_les_motifs_reconnaissent_les_formes_fautives(): void
    {
        $this->assertSame(1, preg_match("/'currency'\s*=>\s*'[A-Za-z]{3}'/", "['currency' => 'eur']"));
        $this->assertSame(1, preg_match("/\\\$currency\s*=\s*'[A-Za-z]{3}'/", "\$currency = 'EUR';"));

        $this->assertSame(0, preg_match("/'currency'\s*=>\s*'[A-Za-z]{3}'/", "['currency' => Devise::plateforme()]"));
        $this->assertSame(0, preg_match("/\\\$currency\s*=\s*'[A-Za-z]{3}'/", '$currency = $booking->currency;'));
    }

    // ── La liste publique des pays ───────────────────────────────────────

    public function test_un_pays_ouvert_dans_ladmin_apparait_dans_la_liste_publique(): void
    {
        Country::factory()->create([
            'iso_code' => 'MA',
            'name' => 'Maroc',
            'currency_code' => 'MAD',
            'is_active' => true,
        ]);

        $liste = app(CountryConfigService::class)->all();

        $this->assertArrayHasKey('MA', $liste, 'Le Maroc est ouvert dans l’admin et invisible côté client.');
        $this->assertSame('MAD', $liste['MA']['currency']);
    }

    /**
     * UN PAYS CRÉÉ MAIS PAS ENCORE OUVERT RESTE INVISIBLE.
     *
     * `CountryCenter` crée tout pays fermé par défaut, pour qu'une faute de frappe ne rende pas un
     * marché commandable. La liste publique doit suivre la même règle, sinon le garde ne sert à
     * rien.
     */
    public function test_un_pays_non_actif_reste_hors_de_la_liste(): void
    {
        Country::factory()->create([
            'iso_code' => 'MA',
            'name' => 'Maroc',
            'currency_code' => 'MAD',
            'is_active' => false,
        ]);

        $this->assertArrayNotHasKey('MA', app(CountryConfigService::class)->all());
    }

    /**
     * TÉMOIN — sans aucun pays en base, le socle répond encore.
     *
     * C'est le cas d'une installation neuve. Sans ce repli, `/api/countries` rendrait un tableau
     * vide et tous les sélecteurs de pays des applications se videraient d'un coup — on aurait
     * remplacé une liste incomplète par aucune liste.
     */
    public function test_temoin_une_base_vide_rend_encore_le_socle(): void
    {
        $this->assertSame(0, Country::query()->count());

        $liste = app(CountryConfigService::class)->all();

        $this->assertArrayHasKey('BE', $liste);
        $this->assertSame('EUR', $liste['BE']['currency']);
    }

    /**
     * ON N'INVENTE PAS UN TAUX DE TVA POUR UN PAYS QU'ON NE CONNAÎT PAS.
     *
     * Le socle porte des champs que la base n'a pas — taux de référence, documents d'identité,
     * pays Stripe. Recopier ceux de la Belgique sur le Maroc produirait un faux qui a l'air d'une
     * donnée, et c'est exactement la classe de défaut que ce lot corrige.
     */
    public function test_un_pays_hors_socle_ne_recupere_pas_la_tva_belge(): void
    {
        Country::factory()->create([
            'iso_code' => 'MA', 'name' => 'Maroc', 'currency_code' => 'MAD', 'is_active' => true,
        ]);

        $maroc = app(CountryConfigService::class)->get('MA');

        $this->assertSame(0.0, $maroc['vat_rate'], 'Le taux belge a été recopié sur le Maroc.');
        $this->assertSame('MA', $maroc['stripe_country']);
    }

    // ─────────────────────────────────────────────────────────────────────

    private function crediter(User $prestataire, float $montant, string $devise): void
    {
        ProviderWalletTransaction::query()->create([
            'provider_user_id' => $prestataire->id,
            'direction' => ProviderWalletTransaction::DIRECTION_CREDIT,
            'type' => ProviderWalletTransaction::TYPE_EARNING,
            'status' => ProviderWalletTransaction::STATUS_AVAILABLE,
            'amount' => $montant,
            'currency' => $devise,
            'occurred_at' => now(),
            'idempotency_key' => 'test:'.$prestataire->id.':'.$devise.':'.$montant,
        ]);
    }
}
