<?php

namespace Tests\Feature\Navigation;

use Illuminate\Routing\Route as RouteObjet;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * AUCUNE PAGE NE DOIT ÊTRE ATTEIGNABLE SEULEMENT EN TAPANT SON URL.
 *
 * POURQUOI CE FICHIER EXISTE. Un audit du 2026-08-05 a montré que la page d'accueil ne citait que
 * quatre routes : ni le moteur de commande (`order.journey`), ni les pages services publiques n'y
 * étaient reliés. Ces deux ensembles ne se citaient qu'entre eux — des boucles fermées, sans porte
 * d'entrée. Côté administration, le catalogue géographique et le suivi d'onboarding étaient dans le
 * même cas, et `admin.home` — une page bien réelle, distincte d'`admin.dashboard` — n'était liée
 * nulle part.
 *
 * CE QUE « ATTEIGNABLE » VEUT DIRE ICI. Pas « citée quelque part » : un lien posé sur une page
 * elle-même inatteignable ne mène nulle part. On part donc des coquilles de navigation et on
 * MARCHE — une route est atteinte si un fichier appartenant à une route déjà atteinte la cite.
 * C'est cette transitivité qui distingue une page reliée d'une page orpheline dans un îlot.
 *
 * CE QUE CE TEST NE PEUT PAS VOIR. Un lien construit dynamiquement (`route($variable)`) ou une URL
 * écrite en dur lui échappent — il signalera alors un FAUX orphelin, et c'est l'URL qu'il faudra
 * corriger, pas la liste ci-dessous.
 *
 * Ce n'est pas théorique : le 2026-08-05, un balayage a trouvé 51 liens écrits en dur, dont celui
 * par lequel un prestataire accepte une mission, et deux qui pointaient vers `/client/dashboard` —
 * un 404, la route étant `/dashboard/client`. Tous passés en `route()`. La mesure ne vaut donc que
 * tant que le dépôt s'y tient : `scratchpad/urls-en-dur.js` refait le balayage à la demande.
 */
class ToutePageEstAtteignableTest extends TestCase
{
    /**
     * Flux techniques : atteints par une redirection externe, un scan, un flux ou un
     * téléchargement. Personne ne doit les trouver dans un menu — les y mettre serait le défaut.
     *
     * @var list<string>
     */
    private const HORS_INTERFACE = [
        'employe.stripe-connect.refresh',        // rappel OAuth de Stripe Connect
        'employe.stripe-connect.return',         // rappel OAuth de Stripe Connect
        'premium.success',                       // retour de paiement Stripe
        'premium.cancel',                        // retour de paiement Stripe
        'assistant.stream',                      // flux SSE, ouvert par le client JS et signé
        'messaging.attachments.download',        // téléchargement d'une pièce jointe par son id
        'employe.missions.qr.validate',          // cible d'un scan de QR code, pas d'un clic
        'scribe',                                // documentation d'API, hors application
        'scribe.openapi',                        // spécification OpenAPI servie à côté de la doc
        'scribe.postman',                        // collection Postman servie à côté de la doc
        // Cible de lien profond : `MissionOfferNotification` l'envoie au prestataire pour qu'il
        // accepte ou refuse. Elle n'a aucune raison de figurer dans un menu — on n'accepte pas une
        // mission qu'on n'a pas reçue. Le lien est passé en `route()` le 2026-08-05 ; il était
        // écrit en dur, donc muet le jour où l'URI changerait.
        'provider.missions.offer',
        // Seule porte de `client.conversations.show` : ce sous-système n'a PAS de page liste.
        // `NewConversationMessageNotification` y mène désormais — elle portait déjà le
        // `conversation_id` dans sa charge utile mais renvoyait au tableau de bord.
        'client.conversations.show',
    ];

    /**
     * Des ROUTES SANS PAGE : `routes/missing-route-fixes-advanced.php` enregistre des replis qui
     * renvoient du texte de remplissage quand la vraie implémentation manque.
     *
     * Les relier serait une faute : on offrirait un menu vers « Export PDF global à implémenter. »
     * ou vers `<h1>Gérer une série récurrente</h1>`. Ce ne sont pas des pages orphelines, ce sont
     * des fonctionnalités annoncées et non écrites — un manque de code, pas un manque de lien.
     *
     * À retirer d'ici le jour où elles sont implémentées : elles redeviendront alors de vraies
     * pages, à relier comme les autres.
     *
     * @var list<string>
     */
    private const BOUCHONS = [
        'admin.export.csv',                      // repli ; le CSV réel est produit dans ExportTools
        'admin.rendezvous.series.edit',          // renvoie du HTML en dur avec l'id de la série
    ];

    /**
     * Routes de démonstration et d'étude, exclues du périmètre sur décision du 2026-08-05.
     *
     * @var list<string>
     */
    private const DEMONSTRATION = [
        'design-system',
        'editorial.study',
        'luxe.landing',
        'premium-scroll.demo',
    ];

    /**
     * Ce qui reste à relier, et qu'on ne veut pas voir GRANDIR.
     *
     * Cette liste est un constat daté, pas une permission : chaque entrée est une page qu'on
     * n'atteint aujourd'hui qu'en tapant son URL. Le test échoue si une route en SORT sans être
     * retirée d'ici (tant mieux, on l'a reliée) comme si une nouvelle y ENTRE (c'est une
     * régression). Les vider est le travail ; les laisser grossir serait le renoncement.
     *
     * @var list<string>
     */
    private const LACUNES_CONNUES = [
        // Exports : appellent un bouton sur leur page parente, pas une entrée de menu.
        // `admin.export.pdf` a été RELIÉ le 2026-08-05 : il l'était déjà en réalité, depuis la
        // CLASSE `ExportTools` montée par `<livewire:admin.export-tools />` sur la page Outils.
        // C'est la mesure qui ne suivait pas les classes des composants imbriqués.
        // `admin.missions.export.pdf` et les deux exports qualité ont été RELIÉS le 2026-08-05 :
        // ils vivaient sur la vue d'`admin.missions.show`, à laquelle la liste des missions ne
        // menait pas. Un lien « Ouvrir la mission » a ouvert la page ET ses trois exports.
        // Les trois exports analytiques client ont été RELIÉS le 2026-08-05 : leurs boutons
        // existaient déjà mais visaient `analytics.export.*` au lieu de `client.analytics.export.*`,
        // donc ne s'affichaient jamais. Retirés d'ici, comme ce test l'exige.
        // `client.exports.bookings.xlsx` a été RELIÉ le 2026-08-05 : la route et son contrôleur
        // existaient, mais AUCUNE page ne l'offrait. Bouton « Exporter en Excel » ajouté sur
        // l'historique client, sa page parente naturelle.
        // Pages de détail : appellent un lien depuis leur liste parente.
        // `admin.recurrence.edit` a été RELIÉ le 2026-08-05 : bouton « Gérer la série » sur la
        // carte mission admin, quand la réservation appartient à une série. Le client avait déjà
        // l'équivalent sur sa propre carte ; l'administration avait la page, sans la porte.
        // `client.booking.tracking.map` a été RELIÉ le 2026-08-05 : bouton « Suivre sur la carte »
        // sur la tuile de suivi, pour les missions confirmées, en route ou sur place.
        // `client.feedback.create` a été RELIÉ le 2026-08-05 : l'historique affichait un avis
        // s'il existait, sans jamais offrir d'en laisser un. Bouton ajouté dans la branche
        // « pas encore de feedback ».
        // `client.tip.booking` a été RELIÉ le 2026-08-05, après correction d'une erreur de MA part :
        // je l'avais écarté en croyant devoir passer par `$rdv->booking`, alors que `HistoriqueClient`
        // interroge `Booking::` directement — la variable s'appelle `$rdv` mais EST un Booking.
        // `employe.missions.show` a été RELIÉ le 2026-08-05 : un bouton « Vue terrain » sur chaque
        // rendez-vous. Le bouton voisin ne fait que déplier les détails DANS la page ; la vue
        // plein écran, elle, n'avait aucun lien.
        // `employe.rate.client` a été RELIÉ le 2026-08-05 : l'évaluation est réciproque, mais
        // seule la moitié client avait un lien. Bouton « Évaluer le client » sur les missions terminées.
        /*
         * `missions.show` — LACUNE ASSUMÉE, sur décision du 2026-08-05.
         *
         * C'est la vue PUBLIQUE d'une mission (`missions/{mission}`). Son unique citant dans tout
         * le dépôt était `components/admin/mission-admin-card.blade.php`, un composant qu'aucune
         * page ne rendait — supprimé le même jour. Elle n'est donc plus citée nulle part.
         *
         * Elle n'a PAS été reliée depuis l'administration : celle-ci dispose déjà
         * d'`admin.missions.show`, et l'envoyer vers la vue publique serait un contresens. Le jour
         * où un usage public existe (partage d'un lien de mission, page de suivi ouverte), c'est
         * de là qu'elle devra être reliée.
         */
        'missions.show',
        // `providers.show` a été RELIÉ le 2026-08-05 — plus exactement, il l'était déjà : la page
        // de recherche liait la fiche par `url('/providers/'.$u->id)`, une URL écrite en dur.
        // Le lien marchait, mais figeait l'URI et échappait à toute analyse. Passé à `route()`.
        // `blog.index` a été RELIÉ le 2026-08-05 : ajouté au pied de page public, où il aurait
        // toujours dû figurer — il n'était cité nulle part dans tout le dépôt.
    ];

    /** Préfixes d'infrastructure : ni pages, ni fonctionnalités visibles. */
    private const INFRASTRUCTURE =
        '/^(api|password|two-factor|verification|sanctum|cashier|livewire|ignition|health|seo|media|presence|webview|phone|google|debugbar|horizon|telescope)\./';

    public function test_aucune_page_n_est_orpheline(): void
    {
        $inatteignables = $this->routesInatteignables();

        $tolerees = array_merge(self::HORS_INTERFACE, self::DEMONSTRATION, self::BOUCHONS, self::LACUNES_CONNUES);

        $nouvelles = array_values(array_diff($inatteignables, $tolerees));

        $this->assertSame([], $nouvelles, sprintf(
            "Ces pages ne sont atteignables qu'en tapant leur URL :\n%s\n\n".
            'Ajoutez-leur une entrée de menu ou un bouton depuis une page déjà atteinte.',
            implode("\n", array_map(static fn (string $n): string => '  - '.$n, $nouvelles)),
        ));
    }

    public function test_les_lacunes_connues_ne_sont_pas_perimees(): void
    {
        $inatteignables = $this->routesInatteignables();

        $reliees = array_values(array_intersect(self::LACUNES_CONNUES, $inatteignables));

        $this->assertSame(
            self::LACUNES_CONNUES,
            $reliees,
            'Des lacunes connues ont été reliées : retirez-les de LACUNES_CONNUES, sinon la liste '.
            'protège des pages qui n’en ont plus besoin et masque les vraies régressions.',
        );
    }

    /**
     * `Route::has('x')` sur un nom qui n'existe pas cache un bouton EN SILENCE.
     *
     * Ce garde protège d'une route absente en production ; il masque tout aussi bien une faute de
     * frappe. Constaté le 2026-08-05 : le tableau de bord analytique client testait
     * `analytics.export.*` au lieu de `client.analytics.export.*`, et ses trois boutons d'export
     * ne s'affichaient JAMAIS — les exports fonctionnaient, personne ne pouvait les déclencher.
     * Une galerie de récurrences promettait de même une page « Mes récurrences » inexistante.
     *
     * Rien ne cassait, rien n'était journalisé : seul ce test peut voir la différence entre
     * « bouton légitimement absent » et « bouton perdu par une faute de frappe ».
     */
    public function test_aucun_garde_route_has_ne_vise_une_route_fantome(): void
    {
        $noms = [];

        foreach ($this->fichiersPhpEtBlade() as $fichier) {
            preg_match_all(
                '/Route::has\(\s*[\'"]([a-zA-Z0-9._\-]+)[\'"]\s*\)/',
                (string) file_get_contents($fichier),
                $m,
            );

            foreach ($m[1] as $nom) {
                $noms[$nom] ??= $fichier;
            }
        }

        // Sans cette borne, une expression cassée rendrait le test vert en ne trouvant plus rien.
        $this->assertGreaterThan(20, count($noms), 'La mesure ne trouve presque aucun Route::has : elle a cessé de mesurer.');

        $fantomes = [];

        foreach ($noms as $nom => $fichier) {
            if (! Route::has($nom)) {
                $fantomes[] = sprintf('%s (dans %s)', $nom, str_replace(base_path().DIRECTORY_SEPARATOR, '', $fichier));
            }
        }

        sort($fantomes);

        $this->assertSame([], $fantomes, sprintf(
            "Ces gardes visent une route qui n'existe pas — le bouton ou la redirection qu'ils ".
            "protègent ne se produira JAMAIS, sans erreur :\n%s",
            implode("\n", array_map(static fn (string $f): string => '  - '.$f, $fantomes)),
        ));
    }

    /**
     * `route('x')` sur un nom inexistant lève au rendu — ou, pire, est rattrapé par un `catch`
     * qui renvoie vers une URL morte.
     *
     * Constaté le 2026-08-05 : `NpsSurveyNotification` appelait `route('nps.survey')`, qui n'existe
     * pas (`client.nps.survey`, oui). L'exception était attrapée et le repli pointait vers `/nps`,
     * que rien ne sert non plus — chaque enquête NPS envoyait vers un 404, silencieusement. Le
     * try/catch avait transformé une erreur bruyante en lien mort.
     *
     * `$request->route('param')` LIT un paramètre d'URL : ce n'est pas une génération de lien, et
     * ce test doit l'ignorer sous peine de crier sur du code correct.
     */
    public function test_aucun_appel_route_ne_vise_une_route_fantome(): void
    {
        $fantomes = [];

        foreach ($this->fichiersPhpEtBlade() as $fichier) {
            $brut = (string) file_get_contents($fichier);

            // Les commentaires citent souvent une route pour EXPLIQUER qu'elle n'existe pas ;
            // les compter ferait échouer le test sur de la prose.
            $texte = preg_replace('#/\*[\s\S]*?\*/|//[^\n]*|\{\{--[\s\S]*?--\}\}#', '', $brut) ?? $brut;

            // Un appel précédé d'un `Route::has()` sur le même nom est du code défensif correct :
            // il demande avant d'appeler, exactement ce que ce test réclame.
            preg_match_all('/Route::has\(\s*[\'"]([a-zA-Z0-9._\-]+)[\'"]\s*\)/', $texte, $gardes);
            $protegees = array_flip($gardes[1]);

            // `(?<![>\$\w])` écarte `$request->route('x')` et `$this->route('x')`.
            preg_match_all('/(?<![>$\w])route\(\s*[\'"]([a-zA-Z0-9._\-]+)[\'"]/', $texte, $m);

            foreach ($m[1] as $nom) {
                if (Route::has($nom) || isset($protegees[$nom])) {
                    continue;
                }

                $court = str_replace(base_path().DIRECTORY_SEPARATOR, '', $fichier);
                $fantomes[$nom.' (dans '.$court.')'] = true;
            }
        }

        $liste = array_keys($fantomes);
        sort($liste);

        $this->assertSame([], $liste, sprintf(
            "Ces appels visent une route qui n'existe pas — au mieux la page tombe, au pire un ".
            "`catch` renvoie vers un lien mort :\n%s",
            implode("\n", array_map(static fn (string $f): string => '  - '.$f, $liste)),
        ));
    }

    /** @return list<string> */
    private function fichiersPhpEtBlade(): array
    {
        $trouves = [];

        foreach ([app_path(), resource_path('views')] as $racine) {
            $iterateur = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($racine));

            /** @var \SplFileInfo $fichier */
            foreach ($iterateur as $fichier) {
                if ($fichier->isFile() && str_ends_with($fichier->getFilename(), '.php')) {
                    $trouves[] = $fichier->getPathname();
                }
            }
        }

        return $trouves;
    }

    /**
     * Le garde-fou de la MESURE elle-même.
     *
     * Une expression cassée, un dossier renommé, et le parcours ne trouverait plus rien à
     * mesurer — le test passerait au vert en ne vérifiant plus rien. Ces bornes rendent ce
     * silence impossible.
     */
    public function test_la_mesure_mesure_encore_quelque_chose(): void
    {
        $perimetre = $this->routesDuPerimetre();
        $racines = $this->racines();

        $this->assertGreaterThan(150, count($perimetre), 'Le périmètre s’est effondré : la mesure a cessé de mesurer.');
        $this->assertGreaterThan(50, count($racines), 'Presque aucune racine trouvée : les coquilles de navigation ne sont plus lues.');
    }

    // ──────────────────────────────────────────────────────────────
    // La mesure
    // ──────────────────────────────────────────────────────────────

    /** @return list<string> */
    private function routesDuPerimetre(): array
    {
        $noms = [];

        /** @var RouteObjet $route */
        foreach (Route::getRoutes() as $route) {
            $nom = $route->getName();

            if ($nom === null || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            if (preg_match(self::INFRASTRUCTURE, $nom) === 1) {
                continue;
            }

            $noms[] = $nom;
        }

        return array_values(array_unique($noms));
    }

    /** Les routes citées par les coquilles de navigation et la page d'accueil. @return list<string> */
    private function racines(): array
    {
        $texte = '';

        foreach ($this->fichiersDesCoquilles() as $fichier) {
            $texte .= (string) file_get_contents($fichier);
        }

        return array_values(array_filter(
            $this->routesDuPerimetre(),
            static fn (string $n): bool => str_contains($texte, "'".$n."'") || str_contains($texte, '"'.$n.'"'),
        ));
    }

    /** @return list<string> */
    private function fichiersDesCoquilles(): array
    {
        $fichiers = [
            resource_path('views/navigation-menu.blade.php'),
            resource_path('views/home.blade.php'),
            /*
             * LA NAVIGATION A DÉMÉNAGÉ, LA MESURE SUIT.
             *
             * Les liens vivaient dans trois tableaux inline de `navigation-menu.blade.php` et dans
             * les deux layouts société. Ils vivent désormais dans `config/modules.php`, servi à la
             * page Modules et à la navbar.
             *
             * Sans cette ligne, ce test ne trouvait plus que 43 racines au lieu de 143 et
             * déclarait injoignable la moitié de l'application — un faux positif massif dû à
             * l'instrument, pas au code.
             */
            config_path('modules.php'),
        ];

        foreach ([resource_path('views/layouts'), resource_path('views/partials')] as $dossier) {
            if (is_dir($dossier)) {
                $fichiers = array_merge($fichiers, $this->bladesDe($dossier));
            }
        }

        return array_values(array_filter($fichiers, 'is_file'));
    }

    /** @return list<string> */
    private function bladesDe(string $dossier): array
    {
        $trouves = glob($dossier.'/*.blade.php') ?: [];

        foreach (glob($dossier.'/*', GLOB_ONLYDIR) ?: [] as $sous) {
            $trouves = array_merge($trouves, $this->bladesDe($sous));
        }

        return $trouves;
    }

    /** @return list<string> */
    private function routesInatteignables(): array
    {
        $perimetre = $this->routesDuPerimetre();
        $parNom = [];

        /** @var RouteObjet $route */
        foreach (Route::getRoutes() as $route) {
            if ($route->getName() !== null) {
                $parNom[$route->getName()] = $route;
            }
        }

        $atteints = $this->racines();
        $aVoir = $atteints;

        while ($aVoir !== []) {
            $nom = array_shift($aVoir);
            $route = $parNom[$nom] ?? null;

            if ($route === null) {
                continue;
            }

            $texte = '';
            foreach ($this->fichiersDe($route) as $fichier) {
                $texte .= (string) file_get_contents($fichier);
            }

            foreach ($perimetre as $autre) {
                if (in_array($autre, $atteints, true)) {
                    continue;
                }

                if (str_contains($texte, "'".$autre."'") || str_contains($texte, '"'.$autre.'"')) {
                    $atteints[] = $autre;
                    $aVoir[] = $autre;
                }
            }
        }

        $restants = array_values(array_diff($perimetre, $atteints));
        sort($restants);

        return $restants;
    }

    /**
     * Les fichiers qui « appartiennent » à une route : sa classe, ses vues, et ce qu'elles
     * incluent — y compris les composants Blade et Livewire, où vivent souvent les liens.
     *
     * @return list<string>
     */
    private function fichiersDe(RouteObjet $route): array
    {
        $action = $route->getActionName();

        if ($action === 'Closure') {
            return [];
        }

        $classe = explode('@', $action)[0];

        if (! str_starts_with($classe, 'App\\')) {
            return [];
        }

        $depart = base_path(str_replace('\\', '/', str_replace('App\\', 'app/', $classe)).'.php');

        if (! is_file($depart)) {
            return [];
        }

        $vus = [];
        $file = [$depart];

        while ($file !== []) {
            $fichier = array_shift($file);

            if (in_array($fichier, $vus, true)) {
                continue;
            }

            $vus[] = $fichier;
            $texte = (string) file_get_contents($fichier);

            foreach ($this->vuesCitees($texte) as $vue) {
                $chemin = resource_path('views/'.str_replace('.', '/', $vue).'.blade.php');

                if (is_file($chemin) && ! in_array($chemin, $vus, true)) {
                    $file[] = $chemin;
                }
            }

            /*
             * Un composant Livewire imbriqué (`<livewire:admin.export-tools />`) cite souvent ses
             * routes dans sa CLASSE, pas dans sa vue : `ExportTools` porte ainsi les deux exports
             * globaux de l'administration. Ne suivre que les vues faisait passer ces exports pour
             * orphelins alors que la page Outils, bien reliée, les monte.
             */
            foreach ($this->classesLivewireCitees($texte) as $classe) {
                if (is_file($classe) && ! in_array($classe, $vus, true)) {
                    $file[] = $classe;
                }
            }
        }

        return $vus;
    }

    /**
     * Les fichiers de classe des composants Livewire montés par balise.
     *
     * `<livewire:admin.export-tools />` correspond à `App\Livewire\Admin\ExportTools`.
     *
     * @return list<string>
     */
    private function classesLivewireCitees(string $texte): array
    {
        preg_match_all('/<livewire:([a-zA-Z0-9._\-\/]+)/', $texte, $m);

        $chemins = [];

        foreach ($m[1] as $nom) {
            $segments = preg_split('/[.\/]/', $nom) ?: [];

            $studly = array_map(
                static fn (string $s): string => str_replace(' ', '', ucwords(str_replace('-', ' ', $s))),
                $segments,
            );

            $chemins[] = app_path('Livewire/'.implode('/', $studly).'.php');
        }

        return array_values(array_unique($chemins));
    }

    /** @return list<string> */
    private function vuesCitees(string $texte): array
    {
        $vues = [];

        preg_match_all('/view\(\s*[\'"]([a-zA-Z0-9._\-\/]+)[\'"]/', $texte, $m);
        $vues = array_merge($vues, $m[1]);

        preg_match_all('/@(?:include|livewire)\(\s*[\'"]([a-zA-Z0-9._\-\/]+)[\'"]/', $texte, $m);
        $vues = array_merge($vues, $m[1]);

        // Les liens vivent souvent dans un composant, pas dans la vue de la page.
        preg_match_all('/<x-([a-zA-Z0-9._\-]+)/', $texte, $m);
        foreach ($m[1] as $nom) {
            $vues[] = 'components.'.$nom;
        }

        preg_match_all('/<livewire:([a-zA-Z0-9._\-]+)/', $texte, $m);
        foreach ($m[1] as $nom) {
            $vues[] = 'livewire.'.str_replace('/', '.', $nom);
        }

        return array_values(array_unique(array_map(
            static fn (string $v): string => str_replace('/', '.', $v),
            $vues,
        )));
    }
}
