<?php

namespace Tests\Feature\DesignSystem;

use Tests\TestCase;

/**
 * UNE LIGNE DE TABLEAU TEINTEE DOIT SUIVRE LE THEME.
 *
 * La transmutation du verre (`glass.css`) rend sombres `bg-white`, `bg-slate-50` et `bg-gray-50`,
 * et repeint leur encre en clair. Elle ne touche PAS aux teintes d'accent — `bg-rose-50`,
 * `bg-indigo-50`… — qui restent CLAIRES, et la reserve du theme laisse donc leur texte sombre.
 *
 * L'accord tient tant que la teinte reste claire. Pose-la sur une LIGNE dans une carte sombre et
 * les deux notions se separent : le fond reste rose pale pendant que l'encre heritee de la carte
 * devient blanche. Mesure du 2026-09-01 sur `/admin/presence`, mode sombre : nom, position,
 * dernier ping et badge tous en blanc sur `rgb(255, 241, 242)` — le tableau etait illisible.
 *
 * La forme juste est deja dans le depot : `bg-blue-50 dark:bg-blue-900/20`.
 */
class UneLigneTeinteeSuitLeThemeTest extends TestCase
{
    /** Les teintes que la transmutation NE reprend PAS — la liste de reserve de `glass.css`. */
    private const TEINTES = 'indigo|blue|sky|violet|emerald|green|amber|yellow|orange|rose|red|teal|cyan|brand|purple|pink';

    /**
     * MESURE, NON TRAITEE. Trois vues portent le meme defaut ; elles n'etaient pas dans la demande
     * qui a fait naitre ce garde-fou. Les retirer d'ici est un une-ligne, comme sur la presence.
     */
    private const CONNUES_NON_TRAITEES = [
        'livewire/admin/i18n/translations-center.blade.php',
        'livewire/admin/order-engine/catalog-center.blade.php',
        'livewire/recurring/edit-recurring-booking.blade.php',
    ];

    public function test_toute_ligne_teintee_porte_sa_variante_sombre(): void
    {
        $fautes = [];

        foreach ($this->toutesLesVues() as $chemin => $source) {
            if (in_array($chemin, self::CONNUES_NON_TRAITEES, true)) {
                continue;
            }

            foreach ($this->lignesTeintees($source) as $balise) {
                $fautes[] = $chemin.' : '.trim(preg_replace('/\s+/', ' ', $balise));
            }
        }

        $this->assertSame([], $fautes, implode("\n", $fautes)."\n\n".
            'Une ligne de tableau porte une teinte claire sans variante sombre : son texte devient '.
            'blanc sur fond pale. Ajouter `dark:bg-<teinte>-900/20`.');
    }

    /**
     * TEMOIN, DANS LES DEUX SENS. Sans lui, le test ci-dessus passerait au vert si le motif etait
     * faux — il mesurerait alors l'absence d'expression reguliere, pas l'absence de defaut.
     */
    public function test_temoin_le_motif_separe_la_forme_fautive_de_la_forme_juste(): void
    {
        $fautif = '<tr class="border-t {{ $stale ? \'bg-rose-50\' : \'\' }}">';
        $this->assertCount(1, $this->lignesTeintees($fautif),
            'Le motif ne reconnait plus la forme qu\'il doit interdire.');

        $juste = '<tr class="bg-blue-50 dark:bg-blue-900/20">';
        $this->assertCount(0, $this->lignesTeintees($juste),
            'Le motif denonce la forme JUSTE : un garde-fou qui crie a tort finit ignore.');

        // Un survol n'est pas une teinte permanente, et les gris SONT repris par la transmutation.
        $this->assertCount(0, $this->lignesTeintees('<tr class="hover:bg-rose-50">'));
        $this->assertCount(0, $this->lignesTeintees('<tr class="bg-slate-50">'));
    }

    /** TEMOIN — les deux vues nommees portent bien le defaut ; sinon la liste est perimee. */
    public function test_temoin_les_exceptions_nommees_portent_encore_le_defaut(): void
    {
        foreach (self::CONNUES_NON_TRAITEES as $vue) {
            $source = (string) file_get_contents(resource_path('views/'.$vue));

            $this->assertNotEmpty($this->lignesTeintees($source),
                $vue.' : le defaut a disparu — retirer cette vue de la liste des exceptions.');
        }
    }

    /** @return list<string> les balises `<tr>` fautives */
    private function lignesTeintees(string $source): array
    {
        preg_match_all('/<tr\b[^>]*>/', $source, $balises);

        $fautives = [];

        foreach ($balises[0] as $balise) {
            // Une teinte claire posee en dur, ni au survol, ni deja sous `dark:`.
            if (! preg_match('/(?<![-:\w])bg-(?:'.self::TEINTES.')-(?:50|100)\b/', $balise)) {
                continue;
            }

            if (! preg_match('/\bdark:bg-/', $balise)) {
                $fautives[] = $balise;
            }
        }

        return $fautives;
    }

    /** @return array<string, string> */
    private function toutesLesVues(): array
    {
        $vues = [];
        $base = resource_path('views');
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));

        foreach ($it as $f) {
            if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.blade.php')) {
                continue;
            }

            $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($f->getPathname(), strlen($base) + 1));

            // `scribe/` est regenere par `artisan scribe:generate` : l'editer serait defait.
            if (str_starts_with($rel, 'scribe/')) {
                continue;
            }

            $vues[$rel] = (string) file_get_contents($f->getPathname());
        }

        return $vues;
    }
}
