<?php

namespace Tests\Feature\DesignSystem;

use Tests\TestCase;

/**
 * UNE COULEUR CHOISIE PAR UN ADMINISTRATEUR NE DOIT PAS POUVOIR RENDRE UN TEXTE ILLISIBLE.
 *
 * La couleur d'un niveau de fidelite n'est contrainte par rien : la fabrique de tests la tire
 * au hasard, la colonne accepte n'importe quel hexadecimal, et elle atterrit en style EN LIGNE
 * — qui bat toutes les regles CSS, donc la reserve du theme ne peut rien pour elle.
 *
 * Mesure d'origine, sur la palette canonique des niveaux, en mode clair :
 *   platine #e5e4e2 : 1,27      or     #ffd700 : 1,40
 *   argent  #c0c0c0 : 1,82      bronze #cd7f32 : 3,14
 */
class UneTeinteDeDonneesResteLisibleTest extends TestCase
{
    /** Les vues qui posent une couleur venue de la base sur du TEXTE. */
    private const VUES = [
        'livewire/admin/loyalty/loyalty-center.blade.php',
        'livewire/client/loyalty-dashboard.blade.php',
    ];

    public function test_aucune_teinte_de_donnees_ne_se_pose_en_couleur_de_texte(): void
    {
        $fautes = [];

        foreach ($this->toutesLesVues() as $chemin => $source) {
            // `style="color: {{ … }}"` : une expression Blade qui devient une couleur de texte.
            if (preg_match_all('/style="[^"]*(?<![-\w])color:\s*\{\{/', $source, $m) > 0) {
                $fautes[] = $chemin.' : '.count($m[0]).' couleur(s) de texte venue(s) d\'une expression';
            }
        }

        $this->assertSame([], $fautes,
            'Une couleur venue des donnees se pose en couleur de texte : elle doit passer par `.brio-teinte`.');
    }

    /**
     * TEMOIN. Sans lui, le test ci-dessus passerait au vert si le motif recherche etait faux —
     * il mesurerait alors l'absence d'expression reguliere, pas l'absence de defaut.
     */
    public function test_temoin_le_motif_reconnait_bien_la_forme_interdite(): void
    {
        $interdit = '<p style="color: {{ $tier->color }};">42</p>';

        $this->assertSame(1, preg_match_all('/style="[^"]*(?<![-\w])color:\s*\{\{/', $interdit),
            'Le motif ne reconnait plus la forme qu\'il doit interdire.');

        /*
         * L'AUTRE SENS COMPTE AUTANT. Un `\b` avant `color` accroche APRES le tiret de
         * `background-color` : le premier jet de ce garde-fou denoncait trois pastilles de
         * couleur, qui ne portent aucun texte. Un garde-fou qui crie a tort finit ignore.
         */
        $this->assertSame(0, preg_match_all('/style="[^"]*(?<![-\w])color:\s*\{\{/', '<span style="background-color: {{ $t->color }}"></span>'),
            'Le motif confond un FOND avec une couleur de texte.');
    }

    public function test_les_vues_concernees_emploient_la_classe_du_systeme(): void
    {
        foreach (self::VUES as $vue) {
            $source = file_get_contents(resource_path('views/'.$vue));

            $this->assertStringContainsString('brio-teinte', $source,
                $vue.' : la teinte des donnees ne passe plus par la classe du systeme.');
        }
    }

    public function test_la_classe_borne_la_clarte_dans_les_deux_themes(): void
    {
        $jetons = file_get_contents(resource_path('css/tokens.css'));
        $classe = file_get_contents(resource_path('css/composants.css'));

        // Deux blocs de bornes : le clair plafonne, le sombre releve.
        $this->assertSame(2, substr_count($jetons, '--brio-teinte-min:'),
            'Les bornes de clarte doivent exister dans les DEUX blocs de theme.');
        $this->assertSame(2, substr_count($jetons, '--brio-teinte-max:'));

        // La declaration de repli d'abord, la relative ensuite : un moteur sans syntaxe
        // relative ignore la seconde et garde la premiere.
        $i = strpos($classe, '.brio-teinte {');
        $this->assertNotFalse($i, 'La classe `.brio-teinte` a disparu du systeme.');

        $corps = substr($classe, $i, 420);

        $this->assertLessThan(strpos($corps, 'oklch(from'), strpos($corps, 'color-mix('),
            'Le repli doit precede la declaration relative, sinon il ne sert a rien.');
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
