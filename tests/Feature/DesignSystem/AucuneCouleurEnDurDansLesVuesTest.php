<?php

namespace Tests\Feature\DesignSystem;

use Tests\TestCase;

/**
 * Une couleur ecrite en dur dans une vue ne suit ni le theme ni le mode sombre.
 * 52 couleurs mesurees, 35 converties ; les 17 restantes sont nommees et motivees ci-dessous.
 */
class AucuneCouleurEnDurDansLesVuesTest extends TestCase
{
    /** Six ou huit chiffres : a trois, `&#123;` et l'ancre `#b2b` passeraient pour des couleurs. */
    private const COULEUR = '/(?<![&\w])#[0-9a-fA-F]{6}(?:[0-9a-fA-F]{2})?\b|rgba?\(\s*\d+\s*[,\s]/i';

    /**
     * Repertoires ou une couleur en dur est la SEULE option possible.
     *
     * @var array<string, string>
     */
    private const HORS_PORTEE = [
        '#/(emails|mail|notifications)/#' => 'un courriel ne peut pas charger de feuille externe',
        '#/(pdf|exports)/|invoice|quote-pdf#' => 'un rendu PDF non plus',
        '#/scribe/#' => 'documentation API generee par un outil',
        '#og-image#' => 'image sociale rendue hors de toute page',
        '#design-system|/dev/|editorial-study|premium-scroll-demo|/luxe#' => 'la vitrine du systeme de design montre les couleurs',
    ];

    /**
     * Ce qui reste en dur, volontairement. Chaque entree porte son motif.
     * Le nombre compte : en ajouter une sans y penser fait tomber le test.
     *
     * @var array<string, array{int, string}>
     */
    private const TOLEREES = [
        'livewire/admin/dashboard/scripts.blade.php' => [1, 'la teinte indigo d’une serie, sans equivalent dans les jetons de statut'],
        'livewire/client/booking-checkout.blade.php' => [3, 'teintes Stripe laissees exprès — le chemin de paiement n’est pas verifiable ici'],
        'livewire/client/client-live-tracking-map.blade.php' => [1, 'teinte du sillage parcouru, distincte du trace'],
        'livewire/admin/order-engine/catalog-center.blade.php' => [1, 'exemple affiche dans un champ, pas une couleur appliquee'],
        'livewire/provider/face-check-page.blade.php' => [1, 'halo blanc du viseur de capture, sans rapport avec la marque'],
    ];

    /** @return array<string, list<string>> */
    private function couleursParVue(): array
    {
        $racine = resource_path('views');
        $iterateur = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($racine));
        $trouvees = [];

        foreach ($iterateur as $fichier) {
            if (! $fichier->isFile() || ! str_ends_with($fichier->getFilename(), '.blade.php')) {
                continue;
            }

            $chemin = str_replace(chr(92), '/', $fichier->getPathname());
            $relatif = ltrim(str_replace(str_replace(chr(92), '/', $racine), '', $chemin), '/');

            foreach (self::HORS_PORTEE as $motif => $_) {
                if (preg_match($motif, $chemin) === 1) {
                    continue 2;
                }
            }

            $code = preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents($chemin)) ?? '';
            // Une couleur dans un <svg> est du dessin, pas du theme.
            $code = preg_replace('#<svg\b.*?</svg>#s', '', $code) ?? $code;
            // Le repli d'un `brioJeton(...)` est VOULU : il sert avant que la feuille soit la.
            $code = preg_replace('/brioJeton\([^)]*\)/', 'brioJeton()', $code) ?? $code;

            if (preg_match_all(self::COULEUR, $code, $m) > 0) {
                $trouvees[$relatif] = $m[0];
            }
        }

        return $trouvees;
    }

    /**
     * TEMOIN — le balayage voit bien des vues, et sait reconnaitre une couleur.
     * Sans lui, un chemin faux rendrait un tableau vide et le garde passerait sur du neant.
     */
    public function test_temoin_le_balayage_lit_les_vues_et_reconnait_une_couleur(): void
    {
        $vues = 0;

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.blade.php')) {
                $vues++;
            }
        }

        $this->assertGreaterThan(500, $vues, 'Le balayage ne voit presque aucune vue : le chemin est faux.');
        $this->assertSame(1, preg_match(self::COULEUR, 'color: #0f172a;'), 'Une hexadecimale doit etre reconnue.');
        $this->assertSame(1, preg_match(self::COULEUR, 'background: rgba(15, 23, 42, .2)'), 'Un rgba doit etre reconnu.');
        $this->assertSame(0, preg_match(self::COULEUR, 'href="#b2b"'), 'Une ancre n’est pas une couleur.');
        $this->assertSame(0, preg_match(self::COULEUR, '&#123;'), 'Une entite HTML non plus.');
    }

    public function test_aucune_vue_n_ecrit_de_couleur_hors_du_systeme_de_design(): void
    {
        $ecarts = [];

        foreach ($this->couleursParVue() as $vue => $couleurs) {
            $tolere = self::TOLEREES[$vue][0] ?? 0;

            if (count($couleurs) > $tolere) {
                $liste = implode(' ', array_slice(array_unique($couleurs), 0, 6));
                $ecarts[] = $tolere === 0
                    ? "{$vue} : ".count($couleurs)." couleur(s) en dur — {$liste}"
                    : "{$vue} : ".count($couleurs).' couleurs, '.$tolere." tolerees — {$liste}";
            }
        }

        $this->assertSame(
            [],
            $ecarts,
            'Employez un jeton : `var(--brio-ink)` en CSS, `window.brioJeton(\'--brio-ink\')` depuis JS.',
        );
    }

    /**
     * Les tolerances ne se perpetuent pas toutes seules : une vue nettoyee doit sortir de la liste,
     * sinon elle autorise en silence une couleur qui reviendrait plus tard.
     */
    public function test_aucune_tolerance_n_est_devenue_inutile(): void
    {
        $reel = $this->couleursParVue();
        $perimees = [];

        foreach (self::TOLEREES as $vue => [$nombre, $motif]) {
            $trouve = count($reel[$vue] ?? []);

            if ($trouve < $nombre) {
                $perimees[] = "{$vue} : {$nombre} tolerees, {$trouve} presentes — abaisser le compte";
            }
        }

        $this->assertSame([], $perimees, 'Une tolerance trop large laisse rentrer une couleur neuve.');
    }
}
