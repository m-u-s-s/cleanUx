<?php

namespace Tests\Feature\A11y;

use Tests\TestCase;

/**
 * UN CHAMP, UN BOUTON ET UNE IMAGE DOIVENT PORTER LEUR NOM.
 *
 * Mesure d'origine : 869 `<label>` dans les vues, 58 seulement reliés à leur champ. 301
 * enveloppaient le leur — c'est correct — et 506 ne faisaient RIEN : du texte posé à côté
 * d'un champ anonyme. Au lecteur d'écran, la phrase était « champ de saisie, vide ».
 *
 * S'y ajoutaient 10 boutons réduits à une icône sans nom, et 15 images sans alternative,
 * dont 11 avatars alors que le nom de la personne était disponible sur place.
 *
 * RIEN NE POUVAIT LE DIRE. Un `<label>` sans `for` est du HTML parfaitement valide ; il
 * s'affiche, il se lit à l'œil, et il ne relie rien. Le compilateur Blade n'a pas d'avis,
 * Tailwind non plus, et la suite mesurait le rendu, pas le lien.
 *
 * LES TROIS FORMES CORRECTES, et une seule ne l'est pas :
 *   `<label for="x">` + `<input id="x">`     le lien explicite
 *   `<label><input …></label>`               le label qui enveloppe son champ
 *   `role="group" aria-labelledby="x"`       un titre au-dessus d'un ENSEMBLE de champs
 *   `<label>` seul, à côté d'un champ        le défaut
 */
class UnChampPorteSonNomTest extends TestCase
{
    /**
     * Les composants qui rendent un `<label>` dont le `for` vient de leur APPELANT.
     *
     * Ils ne peuvent pas se relier eux-mêmes : c'est celui qui les emploie qui sait à quel
     * champ le label appartient. Les exclure ici n'excuse pas leurs appelants, que la même
     * règle attrape.
     *
     * @var list<string>
     */
    private const COMPOSANTS_A_ATTRIBUTS = [
        'components/label.blade.php',
        'components/ui/field.blade.php',
    ];

    public function test_aucun_label_ne_reste_detache(): void
    {
        $detaches = [];

        foreach ($this->vues() as $chemin => $source) {
            if (in_array($chemin, self::COMPOSANTS_A_ATTRIBUTS, true)) {
                continue;
            }

            preg_match_all('/<label\b([^>]*)>(.*?)<\/label>/s', $source, $trouves, PREG_SET_ORDER);

            foreach ($trouves as $label) {
                if (preg_match('/\bfor=/', $label[1]) === 1) {
                    continue;
                }

                // Un label qui ENVELOPPE son champ n'a pas besoin de `for` — que ce soit une
                // balise brute ou un composant qui en rend une.
                if (preg_match('/<(input|select|textarea)\b|<x-[\w.-]*(checkbox|radio|input|select|textarea|toggle|switch)/i', $label[2]) === 1) {
                    continue;
                }

                $detaches[] = $chemin.' — '.trim(preg_replace('/\s+/', ' ', mb_substr($label[2], 0, 40)) ?? '');
            }
        }

        sort($detaches);

        $this->assertSame([], $detaches,
            'Un `<label>` ne relie rien : donnez-lui un `for` et un `id` à son champ, enveloppez le champ, '
            .'ou — s\'il coiffe un ENSEMBLE de champs — employez `role="group"` avec `aria-labelledby`.');
    }

    public function test_aucun_bouton_ne_reste_sans_nom(): void
    {
        $anonymes = [];

        foreach ($this->vues() as $chemin => $source) {
            foreach ($this->balises($source, 'button') as [$ouvrant, $dedans, $ligne]) {
                if (preg_match('/\b(aria-label|aria-labelledby|title)=/', $ouvrant) === 1) {
                    continue;
                }

                // Ce qui reste une fois les balises et les commentaires retirés : le nom visible.
                $visible = preg_replace('/<[^>]+>|\{\{--.*?--\}\}|\s+/s', '', $dedans);

                if ($visible !== '' && $visible !== null) {
                    continue;
                }

                $anonymes[] = $chemin.':'.$ligne;
            }
        }

        sort($anonymes);

        $this->assertSame([], $anonymes,
            'Un bouton n\'a que son icône : au lecteur d\'écran il s\'annonce « bouton », et rien de plus. '
            .'`aria-label` le nomme pour tout le monde, y compris sur un téléphone où `title` n\'existe pas.');
    }

    public function test_aucune_image_ne_reste_sans_alternative(): void
    {
        $muettes = [];

        foreach ($this->vues() as $chemin => $source) {
            // `home.blade.php` est hors périmètre : la page vitrine ne se touche pas.
            if ($chemin === 'home.blade.php') {
                continue;
            }

            preg_match_all('/<img\b(?:"[^"]*"|\'[^\']*\'|[^>"\'])*?>/s', $source, $trouves);

            foreach ($trouves[0] as $balise) {
                if (preg_match('/\balt=/', $balise) === 1) {
                    continue;
                }

                $muettes[] = $chemin.' — '.mb_substr(trim(preg_replace('/\s+/', ' ', $balise) ?? ''), 0, 60);
            }
        }

        sort($muettes);

        $this->assertSame([], $muettes,
            'Une image sans `alt` est annoncée par son URL. `alt=""` la rend muette quand elle est décorative — '
            .'un avatar dont le nom est écrit juste à côté — et un `alt` rempli la nomme quand elle est seule.');
    }

    /**
     * TÉMOIN. Sans lui, les trois tests ci-dessus passeraient au vert si `vues()` ne rendait
     * plus rien, ou si les motifs cessaient de reconnaître ce qu'ils cherchent.
     */
    public function test_temoin_la_mesure_mesure_encore_quelque_chose(): void
    {
        $vues = $this->vues();

        $this->assertGreaterThan(400, count($vues),
            'La lecture des vues ne rend presque rien : les tests ci-dessus ne prouveraient plus rien.');

        $labels = 0;

        foreach ($vues as $source) {
            $labels += preg_match_all('/<label\b/', $source);
        }

        $this->assertGreaterThan(500, $labels, 'Plus aucun `<label>` trouvé : la mesure est cassée.');

        // Les motifs reconnaissent bien ce qu'ils doivent refuser.
        $this->assertSame(1, preg_match('/<label\b([^>]*)>/', '<label class="x">Nom</label>', $m));
        $this->assertSame(0, preg_match('/\bfor=/', $m[1]));

        // …et ce qu'ils doivent laisser passer.
        $this->assertSame(1, preg_match('/\bfor=/', ' for="nom" class="x"'));
    }

    /**
     * Les balises `<nom …>…</nom>` d'une source, avec leur numéro de ligne.
     *
     * UNE BALISE SE FERME AU PREMIER `>` HORS GUILLEMETS. Une lecture naïve en `[^>]*`
     * s'arrête sur le `>` de `->` dans `{{ $objet->id }}` — c'est ce qui avait fait passer
     * trois champs et deux boutons pour ce qu'ils n'étaient pas.
     *
     * @return list<array{0: string, 1: string, 2: int}>
     */
    private function balises(string $source, string $nom): array
    {
        $trouvees = [];
        $motif = '/<'.$nom.'\b(?:"[^"]*"|\'[^\']*\'|[^>"\'])*?>/s';

        preg_match_all($motif, $source, $ouvrants, PREG_OFFSET_CAPTURE);

        foreach ($ouvrants[0] as [$ouvrant, $position]) {
            $fin = strpos($source, '</'.$nom.'>', $position + strlen($ouvrant));

            if ($fin === false) {
                continue;
            }

            $dedans = substr($source, $position + strlen($ouvrant), $fin - $position - strlen($ouvrant));

            // Une balise imbriquée du même nom : on laisse la paire externe de côté.
            if (str_contains($dedans, '<'.$nom)) {
                continue;
            }

            $trouvees[] = [$ouvrant, $dedans, substr_count(substr($source, 0, $position), "\n") + 1];
        }

        return $trouvees;
    }

    /** @return array<string, string> */
    private function vues(): array
    {
        $vues = [];
        $base = resource_path('views');

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));

        foreach ($it as $f) {
            if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.blade.php')) {
                continue;
            }

            $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($f->getPathname(), strlen($base) + 1));

            // `scribe/` est régénérée par `artisan scribe:generate` : l'éditer serait défait.
            if (str_starts_with($rel, 'scribe/')) {
                continue;
            }

            $vues[$rel] = (string) file_get_contents($f->getPathname());
        }

        return $vues;
    }
}
