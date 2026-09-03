<?php

namespace Tests\Feature\Parity;

use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * LES DEUX REGISTRES DISENT LE MEME MODULE.
 *
 * `config/modules.php` remplit le répertoire des applications (`/api/modules`) ;
 * `config/parity.php` porte le mode de livraison et déclenche le contrôle de rendu sans chrome
 * (`/parity-map`). Rien ne les reliait : un écran ajouté au premier restait invisible au second,
 * sans erreur ni test rouge. Vingt et un l'étaient.
 *
 * Un chemin est en règle de trois façons, et d'aucune autre : il figure au registre mobile, il
 * est l'ALIAS d'un chemin qui y figure, ou il est nommé comme n'étant pas une page.
 */
class LeRegistreWebEtLeRegistreMobileDisentLaMemeChoseTest extends TestCase
{
    /**
     * DES ALIAS, PAS DES ECRANS — chemin du répertoire => écran réellement servi.
     *
     * `/prendre-rendez-vous` est une `Route::redirect` vers `/commander` ;
     * `/dashboard/client/analytics` est renvoyé par `RedirigeVersLEspaceFusionne` vers l'espace
     * société fusionné, seul endroit où cet écran existe encore. Les annoncer une seconde fois
     * ferait deux cases pour un seul écran.
     *
     * La destination de chaque ligne est VERIFIEE plus bas : une exemption qui ne mène nulle part
     * n'est plus une décision, c'est un trou.
     *
     * @var array<string, string>
     */
    private const ALIAS = [
        '/prendre-rendez-vous' => '/commander',
        '/dashboard/client/analytics' => '/dashboard/entreprise-client/moi/analytics',
    ];

    /**
     * CE QUI N'EST PAS UNE PAGE, avec son motif.
     *
     * Une seule ligne, et une décision — pas une liste de tâches déguisée. `/presence/me` y
     * figurait : elle rendait du JSON, elle a reçu son écran, la ligne est partie.
     *
     * @var array<string, string>
     */
    private const PAS_DES_PAGES = [
        '/dashboard/employe/stripe-connect/start' => 'Part chez Stripe : une session d’identification bancaire n’a rien à faire dans un WebView embarqué.',
    ];

    public function test_chaque_ecran_du_repertoire_est_annonce_au_mobile(): void
    {
        $mobile = $this->cheminsDuRegistreMobile();
        $manquants = [];

        foreach ($this->cheminsDuRepertoireWeb() as $cle => $chemin) {
            if (array_key_exists($chemin, self::PAS_DES_PAGES)) {
                continue;
            }

            $effectif = self::ALIAS[$chemin] ?? $chemin;

            if (! in_array($effectif, $mobile, true)) {
                $manquants[] = $cle.' → '.$chemin;
            }
        }

        $this->assertSame([], $manquants, implode("\n", [
            'Ces écrans existent sur le web et sont invisibles dans les applications.',
            'Ajoutez-les à config/parity.php, ou nommez-les ici avec leur motif :',
            ...$manquants,
        ]));
    }

    /**
     * TEMOIN — le balayage lit vraiment les deux registres.
     *
     * Sans ce contrôle, une erreur de filtre rendrait deux listes vides, et le test précédent
     * passerait au vert en ne mesurant rien.
     */
    public function test_temoin_les_deux_registres_sont_bien_lus(): void
    {
        $this->assertGreaterThan(40, count($this->cheminsDuRepertoireWeb()));
        $this->assertGreaterThan(100, count($this->cheminsDuRegistreMobile()));
    }

    /** TEMOIN — chaque alias mène à un écran qui, lui, EST annoncé au mobile. */
    public function test_temoin_chaque_alias_mene_a_un_ecran_annonce(): void
    {
        $mobile = $this->cheminsDuRegistreMobile();

        foreach (self::ALIAS as $chemin => $destination) {
            $this->assertContains(
                $destination,
                $mobile,
                "L’alias {$chemin} renvoie vers {$destination}, absent du registre mobile."
            );
        }
    }

    /** TEMOIN — les chemins exemptés existent encore ; une exemption périmée ne se voit pas. */
    public function test_temoin_les_exemptions_designent_des_routes_vivantes(): void
    {
        foreach ([...array_keys(self::ALIAS), ...array_keys(self::PAS_DES_PAGES)] as $chemin) {
            $this->assertNotNull(
                $this->routePour($chemin),
                "L’exemption {$chemin} ne correspond plus à aucune route : retirez-la."
            );
        }
    }

    /** @return array<string, string> clé du répertoire => chemin */
    private function cheminsDuRepertoireWeb(): array
    {
        $cases = [];

        foreach (config('modules.catalogue', []) as $module) {
            if (! in_array($module['context'], ['client', 'employe'], true)) {
                continue;
            }

            $cases[$module['key']] = (string) parse_url(route($module['route'], [], false), PHP_URL_PATH);
        }

        return $cases;
    }

    /** @return list<string> */
    private function cheminsDuRegistreMobile(): array
    {
        return array_map(
            fn (array $module): string => '/'.ltrim((string) $module['path'], '/'),
            config('parity.modules', []),
        );
    }

    private function routePour(string $chemin): ?RouteDefinition
    {
        $cible = trim($chemin, '/');

        foreach (Route::getRoutes() as $route) {
            if ($route->uri() === $cible && in_array('GET', $route->methods(), true)) {
                return $route;
            }
        }

        return null;
    }
}
