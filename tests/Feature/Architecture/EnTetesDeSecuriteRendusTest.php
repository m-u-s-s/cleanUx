<?php

namespace Tests\Feature\Architecture;

use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * LES EN-TÊTES DE SÉCURITÉ DOIVENT SURVIVRE À `config:cache`.
 *
 * POURQUOI CE FICHIER EXISTE. SecurityHeaders et TrustProxies lisaient leurs réglages par
 * env(), depuis app/. Or env() rend null dès que la configuration est mise en cache — c'est
 * l'état normal en production. Les valeurs par défaut (SAMEORIGIN, strict-origin-when-cross-origin,
 * la Permissions-Policy) auraient donc continué de s'afficher en local pendant qu'en production
 * X-Frame-Options serait devenu une chaîne vide, et les proxies de confiance auraient disparu.
 * Aucun test ne l'aurait vu : la suite ne met jamais la config en cache.
 *
 * CE QUE CETTE GARDE MESURE. Le rendu, pas la déclaration : un vrai GET, et les en-têtes
 * réellement posés sur la réponse. Si quelqu'un remet un env() dans le middleware, le premier
 * cas (valeurs par défaut) reste vert tant que .env est lisible, mais le second — celui qui
 * pose une valeur par `config()->set()` — tombe immédiatement, puisque env() ignore la config.
 *
 * ELLE VERROUILLE AUSSI DEUX NUANCES qu'un simple déplacement de réglage casse sans bruit :
 *   - la CSP de repli ne s'applique QU'EN production. Hors production, aucune CSP ;
 *   - « aucun proxy configuré » vaut null, pas un tableau vide.
 */
class EnTetesDeSecuriteRendusTest extends TestCase
{
    /**
     * Symfony garde les proxies de confiance dans un état STATIQUE, partagé par tout le
     * process PHPUnit. On le restaure pour ne pas contaminer les tests suivants.
     *
     * @var array<int, string>
     */
    private array $proxiesInitiaux = [];

    private int $enTetesInitiaux = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->proxiesInitiaux = Request::getTrustedProxies();
        $this->enTetesInitiaux = Request::getTrustedHeaderSet();
    }

    protected function tearDown(): void
    {
        Request::setTrustedProxies($this->proxiesInitiaux, $this->enTetesInitiaux);

        parent::tearDown();
    }

    #[Test]
    public function les_valeurs_par_defaut_sont_rendues_sur_une_vraie_reponse(): void
    {
        $reponse = $this->get('/health');

        $reponse->assertOk();
        $reponse->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $reponse->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $reponse->assertHeader('Permissions-Policy', 'geolocation=(self), camera=(self), microphone=()');
        $reponse->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    #[Test]
    public function une_valeur_personnalisee_de_configuration_est_rendue_telle_quelle(): void
    {
        config([
            'security.x_frame_options' => 'DENY',
            'security.referrer_policy' => 'no-referrer',
            'security.permissions_policy' => 'geolocation=(), camera=(), microphone=()',
        ]);

        $reponse = $this->get('/health');

        $reponse->assertOk();
        $reponse->assertHeader('X-Frame-Options', 'DENY');
        $reponse->assertHeader('Referrer-Policy', 'no-referrer');
        $reponse->assertHeader('Permissions-Policy', 'geolocation=(), camera=(), microphone=()');
    }

    #[Test]
    public function aucune_csp_hors_production_meme_si_le_repli_existe(): void
    {
        $this->assertNotSame(
            '',
            (string) config('security.csp_production_fallback'),
            'Le repli de production doit exister, sinon ce test ne prouve rien.'
        );

        $reponse = $this->get('/health');

        $reponse->assertOk();
        $reponse->assertHeaderMissing('Content-Security-Policy');
    }

    #[Test]
    public function la_csp_de_repli_s_applique_en_production_quand_aucune_csp_n_est_configuree(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['security.csp' => null]);

        $reponse = $this->passeParSecurityHeaders(Request::create('https://brio.test/quelconque'));

        $this->assertSame(
            config('security.csp_production_fallback'),
            $reponse->headers->get('Content-Security-Policy')
        );
    }

    #[Test]
    public function une_csp_configuree_remplace_le_repli_de_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['security.csp' => "default-src 'none'"]);

        $reponse = $this->passeParSecurityHeaders(Request::create('https://brio.test/quelconque'));

        $this->assertSame("default-src 'none'", $reponse->headers->get('Content-Security-Policy'));
    }

    /**
     * Deux lecteurs regardent cette clé : notre constructeur, et le middleware du framework
     * qui fait `$this->proxies() ?: config('trustedproxy.proxies')`. Ce test mesure la
     * confiance RENDUE, donc il tient quel que soit celui des deux qui a servi — ce qui est
     * précisément ce qu'on veut garantir.
     */
    #[Test]
    public function les_proxies_de_confiance_viennent_de_la_configuration(): void
    {
        config(['trustedproxy.proxies' => '*']);

        $requete = Request::create('http://brio.test/quelconque', 'GET', server: ['REMOTE_ADDR' => '10.0.0.9']);
        $requete->headers->set('X-Forwarded-For', '203.0.113.7');

        (new TrustProxies)->handle($requete, fn () => new Response('ok'));

        $this->assertSame('203.0.113.7', $requete->ip());
    }

    #[Test]
    public function sans_proxy_configure_l_ip_du_proxy_reste_l_ip_client(): void
    {
        config(['trustedproxy.proxies' => null]);

        $requete = Request::create('http://brio.test/quelconque', 'GET', server: ['REMOTE_ADDR' => '10.0.0.9']);
        $requete->headers->set('X-Forwarded-For', '203.0.113.7');

        (new TrustProxies)->handle($requete, fn () => new Response('ok'));

        $this->assertSame('10.0.0.9', $requete->ip());
    }

    /**
     * UNE CONFIGURATION NULLE NE DOIT JAMAIS PRODUIRE UN EN-TÊTE VIDE.
     *
     * `(string) null` vaut '' : tant que le middleware lisait la config sans repli, un fichier de
     * config non publié ou une clé mal orthographiée posait un en-tête vide. Un en-tête vide ne
     * protège de rien ET ne se distingue pas d'un en-tête correct dans un journal — la protection
     * disparaissait donc en silence, ce qui était exactement le défaut que le passage de env() vers
     * la config devait fermer.
     */
    #[Test]
    public function une_configuration_nulle_ne_produit_jamais_un_en_tete_vide(): void
    {
        config([
            'security.x_frame_options' => null,
            'security.referrer_policy' => null,
            'security.permissions_policy' => null,
        ]);

        $reponse = $this->get('/health');

        $reponse->assertOk();
        $reponse->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $reponse->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $reponse->assertHeader('Permissions-Policy', 'geolocation=(self), camera=(self), microphone=()');
    }

    /** Même plancher pour la CSP : la production ne doit pas se retrouver sans, sans un mot. */
    #[Test]
    public function une_csp_de_repli_nulle_ne_laisse_pas_la_production_sans_csp(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['security.csp' => null, 'security.csp_production_fallback' => null]);

        $reponse = $this->passeParSecurityHeaders(Request::create('https://brio.test/quelconque'));

        $this->assertSame(
            SecurityHeaders::CSP_PRODUCTION_PAR_DEFAUT,
            $reponse->headers->get('Content-Security-Policy')
        );
    }

    /**
     * LA NORMALISATION DU CONSTRUCTEUR, SEULE CHOSE QUE NOTRE SOUS-CLASSE CHANGE.
     *
     * Les deux tests de confiance ci-dessus mesurent la confiance RENDUE — ce qui est utile, mais
     * les laisse verts même si l'on supprimait entièrement notre constructeur : le middleware du
     * framework fait lui-même `$this->proxies() ?: config('trustedproxy.proxies')`. Ils ne
     * prouvent donc rien sur NOTRE code. Ce test-ci frappe la propriété résultante.
     *
     * La distinction null / tableau vide est le point sensible : seul null laisse le framework
     * accorder sa confiance automatique aux plateformes gérées. Un tableau vide couperait ce
     * repli sans que rien ne le signale.
     */
    #[Test]
    public function le_constructeur_normalise_les_proxies_et_distingue_null_du_tableau_vide(): void
    {
        $lire = static function (): mixed {
            $middleware = new class extends TrustProxies
            {
                public function proxiesNormalises(): mixed
                {
                    return $this->proxies;
                }
            };

            return $middleware->proxiesNormalises();
        };

        config(['trustedproxy.proxies' => '*']);
        $this->assertSame('*', $lire(), 'Le joker doit rester le joker.');

        config(['trustedproxy.proxies' => ['10.0.0.0/8', ' 172.16.0.0/12 ']]);
        $this->assertSame(
            ['10.0.0.0/8', ' 172.16.0.0/12 '],
            $lire(),
            'Une liste déjà normalisée par la config est reprise telle quelle.'
        );

        config(['trustedproxy.proxies' => null]);
        $this->assertNull($lire(), 'Rien de configuré doit rester NULL, jamais un tableau vide.');

        config(['trustedproxy.proxies' => []]);
        $this->assertNull($lire(), 'Un tableau vide se ramène à null : c’est « rien de configuré ».');
    }

    /**
     * La normalisation de la CHAÎNE se fait dans config/trustedproxy.php. On la vérifie là où elle
     * vit : sinon, personne ne mesure le découpage par virgules ni le retrait des espaces.
     */
    #[Test]
    public function la_configuration_decoupe_une_liste_cidr_et_retire_les_espaces(): void
    {
        $normaliser = static function (?string $brut): mixed {
            return match (true) {
                $brut === '*' => '*',
                (bool) $brut => array_map('trim', explode(',', (string) $brut)),
                default => null,
            };
        };

        $this->assertSame(
            ['10.0.0.0/8', '172.16.0.0/12'],
            $normaliser('10.0.0.0/8, 172.16.0.0/12'),
            'Les espaces après les virgules doivent disparaître, sinon le CIDR ne matche jamais.'
        );
        $this->assertSame('*', $normaliser('*'));
        $this->assertNull($normaliser(null));
        $this->assertNull($normaliser(''));
    }

    /**
     * Passe une requête par le middleware seul. On ne peut pas faire un GET en environnement
     * « production » : ForceHttps court-circuite la pile avant SecurityHeaders, et rien ne
     * serait mesuré.
     */
    private function passeParSecurityHeaders(Request $requete): Response
    {
        return (new SecurityHeaders)->handle($requete, fn () => new Response('ok'));
    }
}
