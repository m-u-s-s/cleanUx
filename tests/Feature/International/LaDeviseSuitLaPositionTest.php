<?php

namespace Tests\Feature\International;

use App\Models\Booking;
use App\Models\Country;
use App\Services\International\CountryMarketResolver;
use App\Support\International\DeviseParPays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** LA DEVISE SE DÉDUIT DE LA POSITION, JAMAIS D'UNE PRÉFÉRENCE NI D'UNE CONSTANTE. */
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

    /** UN PAYS INCONNU REND `null`, ET NON `EUR`. C'est le cœur du fichier. */
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

    /** TÉMOIN — la Belgique reste en euros. */
    public function test_temoin_une_commande_en_belgique_reste_en_euros(): void
    {
        Country::factory()->create(['iso_code' => 'BE', 'name' => 'Belgique', 'currency_code' => 'EUR']);

        $this->assertSame('EUR', app(CountryMarketResolver::class)->deviseAttendue(isoPays: 'BE'));
    }

    /** UN MARCHÉ SANS FICHE PAYS RÉPOND QUAND MÊME JUSTE. */
    public function test_un_pays_sans_fiche_repond_depuis_la_table_iso(): void
    {
        $this->assertSame(
            0,
            Country::query()->where('iso_code', 'MA')->count(),
            'Garde-fou du test : une fiche Maroc existante ferait mesurer autre chose.',
        );

        $this->assertSame('MAD', app(CountryMarketResolver::class)->deviseAttendue(isoPays: 'MA'));
    }

    /** LA VALEUR POSÉE PAR UN ADMINISTRATEUR L'EMPORTE SUR LA DÉDUCTION. */
    public function test_la_fiche_pays_prime_sur_la_table_iso(): void
    {
        Country::factory()->create(['iso_code' => 'MA', 'name' => 'Maroc', 'currency_code' => 'EUR']);

        $this->assertSame(
            'EUR',
            app(CountryMarketResolver::class)->deviseAttendue(isoPays: 'MA'),
            'La déduction a écrasé un choix explicite de l’administration.',
        );
    }

    /** UNE RÉSERVATION EXISTANTE SE RELIT DEPUIS SON PROPRE PAYS. */
    public function test_une_reservation_relit_la_devise_de_son_pays(): void
    {
        Country::factory()->create(['iso_code' => 'MA', 'name' => 'Maroc', 'currency_code' => 'MAD']);

        // NI ZONE NI CODE POSTAL, ET C'EST TOUT L'INTERET DU CAS.
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

    /** AUCUN CHEMIN DE CRÉATION NE DOIT ÉCRIRE UNE DEVISE EN DUR. */
    public function test_aucun_ecrivain_de_reservation_ne_fixe_la_devise(): void
    {
        $ecrivains = [
            'app/Actions/Booking/CreateBookingFromApiAction.php',
            'app/Services/Booking/CreateBookingAction.php',
            'app/Services/Assistant/Tools/Implementations/CreateBookingTool.php',
            // L'estimation rendue au client porte une devise : elle doit venir de la position, et
            // c'est ce controleur qui la resout depuis la zone, pas le moteur de calcul.
            'app/Http/Controllers/Api/Client/BookingEstimateController.php',
        ];

        // TOUS LES ÉCRIVAINS FAUTIFS D'UN COUP.
        $fautifs = [];

        foreach ($ecrivains as $chemin) {
            $source = (string) file_get_contents(base_path($chemin));

            if ($source === '') {
                $fautifs[] = "{$chemin} : introuvable, le test ne mesure plus rien";

                continue;
            }

            // On cherche `'currency' => '…'` : une AFFECTATION littérale.
            if (preg_match("/'currency'\s*=>\s*'[A-Za-z]{3}'/", $source) === 1) {
                $fautifs[] = "{$chemin} : fixe une devise en dur";
            }

            // DEUX PORTES D'ENTRÉE SUR LA MÊME AUTORITÉ, et les deux sont acceptables.
            if (! str_contains($source, 'deviseAttendue') && ! str_contains($source, 'effectiveCurrency')) {
                $fautifs[] = "{$chemin} : n'appelle plus l'autorité commune";
            }
        }

        $this->assertSame(
            [],
            $fautifs,
            'La devise doit venir de `CountryMarketResolver`, qui la déduit de la position. '
            .'Un fichier qui en décide seul rouvre la divergence que ce test ferme.',
        );
    }

    /** TÉMOIN DU MOTIF — il sait reconnaître une devise en dur. */
    public function test_temoin_le_motif_reconnait_une_devise_en_dur(): void
    {
        $this->assertSame(1, preg_match("/'currency'\s*=>\s*'[A-Za-z]{3}'/", "['currency' => 'EUR']"));
        $this->assertSame(0, preg_match("/'currency'\s*=>\s*'[A-Za-z]{3}'/", "['currency' => \$resolveur->deviseAttendue()]"));
    }
}
