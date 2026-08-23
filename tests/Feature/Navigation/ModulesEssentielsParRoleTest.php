<?php

namespace Tests\Feature\Navigation;

use App\Support\Navigation\ModuleCatalogue;
use Tests\TestCase;

/**
 * CHAQUE RÔLE DOIT DISPOSER DES MODULES DONT IL A BESOIN.
 *
 * Le catalogue a été bâti à partir des registres de navigation, qui ne couvraient que les pages de
 * tableau de bord — `admin/*` et `dashboard/*`. Les pages TRANSVERSALES vivent ailleurs :
 * `user/profile`, `notifications`, `aide`, `legal/*`. Elles n'appartiennent à aucun tableau de
 * bord, donc elles n'appartenaient à AUCUN rôle : cinq contextes sur cinq n'avaient ni profil, ni
 * notifications, ni mentions légales dans leur page Modules.
 *
 * ── POURQUOI CE FICHIER ACCUMULE AU LIEU D'AFFIRMER À CHAQUE TOUR ────────────────────────────
 *
 * Il vérifiait vingt-huit cas dans quatre méthodes, avec une assertion à l'intérieur de chaque
 * boucle. Un `assertContains` qui échoue INTERROMPT la méthode : si le profil manquait à trois
 * contextes, la sortie n'en nommait qu'un. On corrigeait, on relançait, on découvrait le suivant.
 *
 * Chaque contrôle collecte donc TOUS ses manques et les affirme d'un coup. Une seule exécution
 * dit tout ce qui ne va pas — et le nombre de méthodes baisse sans qu'un seul cas soit perdu.
 */
class ModulesEssentielsParRoleTest extends TestCase
{
    private const CONTEXTES = ['client', 'employe', 'admin', 'client-company', 'provider-company'];

    /** Ce qu'un compte doit atteindre quel que soit son rôle. */
    private const TRANSVERSAUX = ['profile.show', 'notifications.index', 'help.center', 'terms.show'];

    /**
     * Ce que chaque famille de contextes doit porter EN PLUS des transversaux.
     *
     * @var array<string, list<string>>
     */
    private const PROPRES = [
        'employe' => ['provider.onboarding', 'presence.me'],
        'provider-company' => ['provider.onboarding', 'presence.me'],
        'client' => ['booking.create'],
        'client-company' => ['booking.create'],
    ];

    /** @return list<string> */
    private function routesDe(string $contexte): array
    {
        return ModuleCatalogue::pourContexte($contexte)
            ->flatMap(fn (array $groupe) => array_column($groupe['modules'], 'route'))
            ->all();
    }

    /**
     * TÉMOIN — le catalogue rend bien quelque chose pour chaque contexte.
     *
     * Sans ce contrôle, les deux suivants passeraient au vert le jour où `pourContexte()` rendrait
     * une collection vide : aucun manque relevé sur zéro route examinée.
     */
    public function test_temoin_chaque_contexte_expose_des_modules(): void
    {
        $vides = [];

        foreach (self::CONTEXTES as $contexte) {
            $n = count($this->routesDe($contexte));

            if ($n < 5) {
                $vides[] = sprintf('%s → %d module(s)', $contexte, $n);
            }
        }

        $this->assertSame([], $vides, 'Ces contextes n’exposent presque rien : la mesure ne prouverait rien.');
    }

    public function test_chaque_role_dispose_de_ses_modules(): void
    {
        $manques = [];

        foreach (self::CONTEXTES as $contexte) {
            $routes = $this->routesDe($contexte);
            $attendues = array_merge(self::TRANSVERSAUX, self::PROPRES[$contexte] ?? []);

            foreach ($attendues as $route) {
                if (! in_array($route, $routes, true)) {
                    $manques[] = sprintf('%s → %s', $contexte, $route);
                }
            }
        }

        $this->assertSame([], $manques, 'Ces modules manquent à ces rôles.');
    }

    public function test_aucun_contexte_ne_laisse_une_categorie_essentielle_vide(): void
    {
        /*
         * Trois catégories doivent être servies partout : qui je suis (`comptes`), ce qu'on me dit
         * (`communication`), et les textes qui m'engagent (`plateforme`). Une page Modules qui n'a
         * rien à montrer dans ces trois-là n'est pas un répertoire, c'est une liste partielle.
         */
        $manques = [];

        foreach (self::CONTEXTES as $contexte) {
            $categories = ModuleCatalogue::pourContexte($contexte)->pluck('category')->all();

            foreach (['comptes', 'communication', 'plateforme'] as $essentielle) {
                if (! in_array($essentielle, $categories, true)) {
                    $manques[] = sprintf('%s → rien en « %s »', $contexte, $essentielle);
                }
            }
        }

        $this->assertSame([], $manques, 'Ces catégories sont vides pour ces contextes.');
    }

    public function test_les_modules_transversaux_ne_sont_declares_qu_une_fois(): void
    {
        // Ils portent le contexte `*`. Les recopier dans les cinq contextes serait cinq occasions
        // d'en oublier un — c'est exactement ce que le registre unique a supprimé.
        $partages = collect(config('modules.catalogue'))->where('context', '*');

        $this->assertGreaterThan(0, $partages->count(), 'Aucun module transversal déclaré.');

        $doublons = $partages->pluck('route')
            ->countBy()
            ->filter(fn (int $n) => $n > 1)
            ->keys()
            ->all();

        $this->assertSame([], $doublons, 'Ces modules transversaux sont déclarés plusieurs fois.');
    }
}
