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

    /*
     * LES DEUX MOTIFS SONT DES CONSTANTES, ET LE TEMOIN EMPLOIE LES MEMES.
     *
     * Ils etaient recopies : le balayage et son temoin portaient deux versions de la meme
     * expression, dont l'une mal echappee -- `"\$currency"` interpole la variable au lieu de
     * chercher le texte, et le test tombait sur « Undefined variable ». Le temoin, lui, restait
     * vert : il eprouvait une expression que le code n'utilisait pas.
     *
     * Un temoin qui mesure une COPIE ne prouve rien de l'original. Une seule definition, donc.
     */

    /** `'currency' => 'EUR'` — l'affectation d'un litteral. */
    private const MOTIF_AFFECTATION = "/'currency'\s*=>\s*'[A-Za-z]{3}'/";

    /** `$currency = 'EUR'` — le defaut de parametre ou de propriete. */
    private const MOTIF_DEFAUT = "/\\\$currency\s*=\s*'[A-Za-z]{3}'/";

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
            preg_match(self::MOTIF_AFFECTATION, $source),
            'Le pourboire fixe encore une devise en dur.',
        );

        // Garde-fou : la réservation porte bien la devise qu'on croit.
        $this->assertSame('MAD', $reservation->refresh()->currency);
    }

    /**
     * PLUS AUCUNE DEVISE LITTÉRALE DANS `app/` — ON BALAIE TOUT, PLUS UNE LISTE BLANCHE.
     *
     * LA PREMIÈRE VERSION DE CE TEST ÉNUMÉRAIT HUIT FICHIERS, ceux qu'on venait de reprendre. Elle
     * était verte pendant que `TradePricingEngine` continuait d'étiqueter « EUR » le prix affiché
     * au client et le devis du prestataire — un chantier marocain chiffré au tarif marocain et
     * présenté en euros. Une liste blanche ne protège que de ce qu'on a déjà vu ; c'est un
     * inventaire, pas un garde.
     *
     * On balaie donc `app/` en entier. Deux exceptions, et les deux sont des TABLES par pays où
     * chaque `'eur'` est la donnée juste de sa ligne, pas un repli : {@see StripeCountryMapper} et
     * {@see CountryConfigService}. Les inscrire ici les rend visibles ; les
     * omettre du balayage les aurait rendues invisibles.
     */
    public function test_aucune_devise_litterale_ne_subsiste_dans_app(): void
    {
        /*
         * LES TABLES PAR PAYS, où un code de devise EST la donnée.
         *
         * `StripeCountryMapper` dit dans quelle monnaie chaque pays règle chez Stripe ;
         * `CountryConfigService` porte le socle servi quand la base est vide. Y remplacer les
         * littéraux par un appel ferait dire à toutes les lignes la même chose, c'est-à-dire
         * détruirait l'information.
         */
        $tablesParPays = [
            'app/Services/Payments/StripeCountryMapper.php',
            'app/Services/Country/CountryConfigService.php',
        ];

        $coupables = [];

        foreach ($this->sourcesDeApp() as $chemin => $source) {
            if (in_array($chemin, $tablesParPays, true)) {
                continue;
            }

            // L'AFFECTATION d'un littéral, dans les deux casses : Stripe attend des minuscules, et
            // c'est sous cette forme que le transfert de versement se trompait.
            if (preg_match(self::MOTIF_AFFECTATION, $source) === 1) {
                $coupables[] = "{$chemin} affecte une devise littérale";
            }

            // Et le défaut de paramètre, qui est ce qui vidait le portefeuille d'un prestataire
            // payé dans une autre monnaie.
            if (preg_match(self::MOTIF_DEFAUT, $source) === 1) {
                $coupables[] = "{$chemin} impose une devise par défaut";
            }
        }

        $this->assertSame(
            [],
            $coupables,
            'Une devise est écrite en dur. Employer `Devise::plateforme()` pour un montant émis par '
            .'la plateforme, ou `CountryMarketResolver::deviseAttendue()` quand une position est '
            .'connue.
'.implode('
', $coupables),
        );
    }

    /**
     * TÉMOIN DE PORTÉE — le balayage lit bien tout `app/`.
     *
     * Sans lui, le test précédent serait vert sur un chemin faux ou un itérateur vide : il
     * compterait zéro coupable parmi zéro fichier, et n'annoncerait rien d'autre que sa propre
     * impuissance. C'est exactement ce qui rend une liste blanche dangereuse.
     */
    public function test_temoin_le_balayage_lit_bien_tout_app(): void
    {
        $sources = $this->sourcesDeApp();

        $this->assertGreaterThan(500, count($sources));
        $this->assertArrayHasKey('app/Services/Pricing/TradePricingEngine.php', $sources);
        $this->assertArrayHasKey('app/Services/Payments/ProviderWalletService.php', $sources);
    }

    /**
     * TÉMOIN DU MOTIF — il reconnaît les deux formes fautives, et épargne les innocentes.
     *
     * Sans lui, le test précédent serait vert sur une expression qui ne mord jamais.
     */
    public function test_temoin_les_motifs_reconnaissent_les_formes_fautives(): void
    {
        $this->assertSame(1, preg_match(self::MOTIF_AFFECTATION, "['currency' => 'eur']"));
        $this->assertSame(1, preg_match(self::MOTIF_DEFAUT, "\$currency = 'EUR';"));

        $this->assertSame(0, preg_match(self::MOTIF_AFFECTATION, "['currency' => Devise::plateforme()]"));
        $this->assertSame(0, preg_match(self::MOTIF_DEFAUT, '$currency = $booking->currency;'));
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

    /**
     * Toutes les sources de `app/`, chemin relatif => contenu.
     *
     * @return array<string, string>
     */
    private function sourcesDeApp(): array
    {
        $sources = [];

        $iterateur = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('app'), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterateur as $fichier) {
            if ($fichier->getExtension() !== 'php') {
                continue;
            }

            $chemin = str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                str_replace(base_path().DIRECTORY_SEPARATOR, '', $fichier->getPathname()),
            );

            $sources[$chemin] = (string) file_get_contents($fichier->getPathname());
        }

        return $sources;
    }

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
