<?php

namespace Tests\Feature\International;

use App\Models\Booking;
use App\Models\Country;
use App\Services\International\CountryMarketResolver;
use App\Support\International\DeviseParPays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LA DEVISE SE DÉDUIT DE LA POSITION, JAMAIS D'UNE PRÉFÉRENCE NI D'UNE CONSTANTE.
 *
 * IL Y AVAIT TROIS RÉPONSES À CETTE QUESTION, selon le chemin de création emprunté :
 *
 *   `CreateBookingAction`         le marché-pays — la bonne
 *   `CreateBookingFromApiAction`  `preferred_currency` du COMPTE client
 *   `CreateBookingTool`           `'EUR'` écrit en dur
 *
 * La deuxième est la plus trompeuse. Une préférence de compte dit ce que le client aime VOIR, pas
 * ce dans quoi la prestation se paie : un profil réglé sur l'euro commandant un ménage à
 * Casablanca produisait une réservation en euros, pendant que le prix, lui, venait bien du marché
 * marocain. Deux nombres, deux monnaies, aucune alerte.
 *
 * ET LE FORMULAIRE D'ADMINISTRATION PROPOSAIT `EUR` À TOUT PAYS. Ajouter le Maroc laissait donc
 * `EUR` en place à moins d'y penser. Une valeur pré-remplie juste vingt fois sur vingt-cinq est le
 * pire des cas : on cesse de la lire.
 *
 * ── CE QUE CE FICHIER VERROUILLE ──────────────────────────────────────────────────────────────
 *
 * Que la Belgique et la France paient en euros, que le Maroc paie en dirhams, que ce soit la
 * POSITION qui le décide, et qu'une valeur posée par un administrateur l'emporte toujours sur la
 * déduction — sans quoi on remplacerait une devise fausse par une devise imposée.
 */
class LaDeviseSuitLaPositionTest extends TestCase
{
    use RefreshDatabase;

    /** L'exemple donné : Belgique et France en euros, Maroc en dirhams. */
    public function test_chaque_pays_a_la_devise_de_sa_monnaie(): void
    {
        $this->assertSame('EUR', DeviseParPays::pour('BE'));
        $this->assertSame('EUR', DeviseParPays::pour('FR'));
        $this->assertSame('MAD', DeviseParPays::pour('MA'));

        // Et quelques voisins qui ne sont PAS en euros, pour que la table ne soit pas
        // « tout le monde en EUR sauf le Maroc ».
        $this->assertSame('CHF', DeviseParPays::pour('CH'));
        $this->assertSame('GBP', DeviseParPays::pour('GB'));
        $this->assertSame('TND', DeviseParPays::pour('TN'));
    }

    /** La casse et les espaces d'une saisie ne changent pas la réponse. */
    public function test_le_code_pays_est_normalise(): void
    {
        $this->assertSame('MAD', DeviseParPays::pour('ma'));
        $this->assertSame('MAD', DeviseParPays::pour('  Ma '));
    }

    /**
     * UN PAYS INCONNU REND `null`, ET NON `EUR`.
     *
     * C'est le cœur du fichier. Un repli muet sur l'euro donne une réponse fausse avec l'assurance
     * d'une réponse juste — exactement le défaut corrigé. L'appelant doit pouvoir savoir qu'il ne
     * sait pas.
     */
    public function test_un_pays_inconnu_ne_repond_pas_euro_par_defaut(): void
    {
        $this->assertNull(DeviseParPays::pour('ZZ'));
        $this->assertNull(DeviseParPays::pour(null));
        $this->assertNull(DeviseParPays::pour(''));
    }

    // ── La résolution depuis la position ─────────────────────────────────

    public function test_une_commande_au_maroc_est_en_dirhams(): void
    {
        Country::factory()->create(['iso_code' => 'MA', 'name' => 'Maroc', 'currency_code' => 'MAD']);

        $devise = app(CountryMarketResolver::class)->deviseAttendue(isoPays: 'MA');

        $this->assertSame('MAD', $devise);
    }

    /**
     * TÉMOIN — la Belgique reste en euros.
     *
     * Sans lui, le test précédent passerait au vert sur une implémentation qui rendrait « MAD »
     * partout, ou qui aurait cassé le cas nominal, c'est-à-dire la totalité des commandes
     * existantes.
     */
    public function test_temoin_une_commande_en_belgique_reste_en_euros(): void
    {
        Country::factory()->create(['iso_code' => 'BE', 'name' => 'Belgique', 'currency_code' => 'EUR']);

        $this->assertSame('EUR', app(CountryMarketResolver::class)->deviseAttendue(isoPays: 'BE'));
    }

    /**
     * UN MARCHÉ SANS FICHE PAYS RÉPOND QUAND MÊME JUSTE.
     *
     * C'est le cas d'un pays tout juste ouvert : l'adresse existe avant le maillage géographique.
     * Toutes les pistes de résolution passent par une table et rendaient `null`, si bien que le
     * contexte retombait sur la devise de base — l'euro — pour une commande passée au Maroc.
     */
    public function test_un_pays_sans_fiche_repond_depuis_la_table_iso(): void
    {
        $this->assertSame(
            0,
            Country::query()->where('iso_code', 'MA')->count(),
            'Garde-fou du test : une fiche Maroc existante ferait mesurer autre chose.',
        );

        $this->assertSame('MAD', app(CountryMarketResolver::class)->deviseAttendue(isoPays: 'MA'));
    }

    /**
     * LA VALEUR POSÉE PAR UN ADMINISTRATEUR L'EMPORTE SUR LA DÉDUCTION.
     *
     * Une plateforme peut légitimement facturer en euros depuis un pays qui n'y appartient pas.
     * Si la table ISO écrasait la fiche pays, on aurait remplacé une devise fausse par une devise
     * imposée — un défaut symétrique, et tout aussi difficile à voir.
     */
    public function test_la_fiche_pays_prime_sur_la_table_iso(): void
    {
        Country::factory()->create(['iso_code' => 'MA', 'name' => 'Maroc', 'currency_code' => 'EUR']);

        $this->assertSame(
            'EUR',
            app(CountryMarketResolver::class)->deviseAttendue(isoPays: 'MA'),
            'La déduction a écrasé un choix explicite de l’administration.',
        );
    }

    /**
     * UNE RÉSERVATION EXISTANTE SE RELIT DEPUIS SON PROPRE PAYS.
     *
     * `resolveForRendezVous()` ne consultait que des tables — site, zone, code postal — et rendait
     * donc `null` sur un marché neuf. Le pays écrit sur la réservation est la position sous sa
     * forme la plus brute : ce que le client a saisi.
     */
    public function test_une_reservation_relit_la_devise_de_son_pays(): void
    {
        Country::factory()->create(['iso_code' => 'MA', 'name' => 'Maroc', 'currency_code' => 'MAD']);

        /*
         * NI ZONE NI CODE POSTAL, ET C'EST TOUT L'INTERET DU CAS.
         *
         * La fabrique en cree par defaut, et ils portent leur propre pays -- une zone de service
         * est un signal de position PLUS FORT qu'un texte saisi, et l'ordre de resolution a raison
         * de les preferer. Le cran qu'on eprouve ici est celui d'apres : un marche ou l'adresse
         * existe avant le maillage geographique.
         */
        $reservation = Booking::factory()->create([
            'country' => 'MA',
            'service_zone_id' => null,
            'postal_code_id' => null,
        ]);

        $resolveur = app(CountryMarketResolver::class);

        $this->assertSame('MAD', $resolveur->effectiveCurrency($resolveur->resolveForRendezVous($reservation)));
    }

    /** TÉMOIN — une réservation belge relue reste en euros. */
    public function test_temoin_une_reservation_belge_reste_en_euros(): void
    {
        Country::factory()->create(['iso_code' => 'BE', 'name' => 'Belgique', 'currency_code' => 'EUR']);

        $reservation = Booking::factory()->create([
            'country' => 'BE',
            'service_zone_id' => null,
            'postal_code_id' => null,
        ]);

        $resolveur = app(CountryMarketResolver::class);

        $this->assertSame('EUR', $resolveur->effectiveCurrency($resolveur->resolveForRendezVous($reservation)));
    }

    // ── Le garde-fou contre le retour des constantes ─────────────────────

    /**
     * AUCUN CHEMIN DE CRÉATION NE DOIT ÉCRIRE UNE DEVISE EN DUR.
     *
     * Les trois écrivains ont été ramenés sur la même autorité. Rien n'empêche un quatrième
     * d'apparaître avec son propre `'EUR'` — c'est ainsi que les trois premiers sont nés, un par un,
     * chacun raisonnable isolément. Ce test lit les sources et refuse la constante.
     */
    public function test_aucun_ecrivain_de_reservation_ne_fixe_la_devise(): void
    {
        $ecrivains = [
            'app/Actions/Booking/CreateBookingFromApiAction.php',
            'app/Services/Booking/CreateBookingAction.php',
            'app/Services/Assistant/Tools/Implementations/CreateBookingTool.php',
        ];

        foreach ($ecrivains as $chemin) {
            $source = (string) file_get_contents(base_path($chemin));

            $this->assertNotSame('', $source, "{$chemin} est introuvable : le test ne mesure plus rien.");

            /*
             * On cherche `'currency' => '…'` : une AFFECTATION littérale. Les mentions d'une devise
             * ailleurs — un repli documenté, un libellé — ne sont pas le sujet ; ce qui compte est
             * qu'aucun de ces fichiers ne DÉCIDE de la devise sans passer par le résolveur.
             */
            $this->assertSame(
                0,
                preg_match("/'currency'\s*=>\s*'[A-Za-z]{3}'/", $source),
                "{$chemin} fixe une devise en dur. Elle doit venir de "
                .'`CountryMarketResolver::deviseAttendue()`, qui la déduit de la position.',
            );

            /*
             * DEUX PORTES D'ENTREE SUR LA MEME AUTORITE, et les deux sont acceptables.
             *
             * `deviseAttendue()` sert aux appelants qui n'ont qu'une adresse ; `effectiveCurrency()`
             * a ceux qui ont deja construit le contexte marche-pays -- c'est le cas de
             * `CreateBookingAction`, qui l'emploie aussi pour le taux de taxe et le multiplicateur.
             * Exiger la premiere l'aurait fait passer par un detour sans rien gagner. Ce qui compte
             * est qu'aucun fichier ne decide seul.
             */
            $this->assertTrue(
                str_contains($source, 'deviseAttendue') || str_contains($source, 'effectiveCurrency'),
                "{$chemin} n’appelle plus l’autorité commune : la divergence recommence.",
            );
        }
    }

    /**
     * TÉMOIN DU MOTIF — il sait reconnaître une devise en dur.
     *
     * Sans lui, le test précédent serait vert sur une expression qui ne mord jamais, et
     * n'annoncerait rien d'autre que sa propre impuissance.
     */
    public function test_temoin_le_motif_reconnait_une_devise_en_dur(): void
    {
        $this->assertSame(1, preg_match("/'currency'\s*=>\s*'[A-Za-z]{3}'/", "['currency' => 'EUR']"));
        $this->assertSame(0, preg_match("/'currency'\s*=>\s*'[A-Za-z]{3}'/", "['currency' => \$resolveur->deviseAttendue()]"));
    }
}
