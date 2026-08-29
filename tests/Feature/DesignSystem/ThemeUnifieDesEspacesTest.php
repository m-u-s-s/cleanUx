<?php

namespace Tests\Feature\DesignSystem;

use Tests\TestCase;

/** TOUS LES ESPACES D'OUTIL PARTAGENT LE THÈME DE LA CONSOLE D'ADMINISTRATION. */
class ThemeUnifieDesEspacesTest extends TestCase
{
    /**
     * Les répertoires de vues qui doivent suivre l'idiome admin.
     *
     * @return list<string>
     */
    private function repertoiresDOutil(): array
    {
        return [
            resource_path('views/livewire/provider-company'),
            resource_path('views/livewire/client-company'),
        ];
    }

    /** @return list<string> */
    private function fichiers(): array
    {
        $fichiers = [];

        foreach ($this->repertoiresDOutil() as $repertoire) {
            $iterateur = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($repertoire));

            foreach ($iterateur as $fichier) {
                if ($fichier->isFile() && str_ends_with($fichier->getFilename(), '.blade.php')) {
                    $fichiers[] = $fichier->getPathname();
                }
            }
        }

        sort($fichiers);

        return $fichiers;
    }

    /** Le contenu d'une vue, COMMENTAIRES BLADE RETIRÉS. */
    private function codeDe(string $chemin): string
    {
        return preg_replace('/\{\{--.*?--\}\}/s', '', file_get_contents($chemin)) ?? '';
    }

    public function test_aucun_espace_d_outil_ne_repose_sur_une_surface_sombre(): void
    {
        // `bg-slate-700/800/900/950` en FOND : c'est ce qui faisait de l'espace prestataire une application à part.
        $interdites = '/(?<!dark:)\b(bg|divide)-slate-(700|800|900|950)\b/';

        $coupables = [];

        foreach ($this->fichiers() as $fichier) {
            $contenu = $this->codeDe($fichier);

            if (preg_match_all($interdites, $contenu, $trouvees)) {
                $coupables[basename($fichier)] = array_unique($trouvees[0]);
            }
        }

        $this->assertSame(
            [],
            $coupables,
            "Surface sombre dans un espace d'outil — la console d'administration est claire :\n"
                .json_encode($coupables, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }

    public function test_aucun_espace_d_outil_ne_reintroduit_un_accent_a_lui(): void
    {
        // L'accent est celui de `brio-btn-primary` : sky.
        $interdites = '/\b(bg-purple-(500|600|700)|text-purple-(600|700)|border-purple-(500|600)|bg-amber-(500|600)|text-amber-(400|500))\b/';

        $coupables = [];

        foreach ($this->fichiers() as $fichier) {
            $contenu = $this->codeDe($fichier);

            if (preg_match_all($interdites, $contenu, $trouvees)) {
                $coupables[basename($fichier)] = array_unique($trouvees[0]);
            }
        }

        $this->assertSame(
            [],
            $coupables,
            "Accent propre à un espace — l'accent du produit est `sky`, celui de brio-btn-primary :\n"
                .json_encode($coupables, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }

    public function test_aucun_layout_d_outil_ne_force_le_mode_sombre(): void
    {
        // `layouts/provider-company` écrivait `<html … class="dark">` EN DUR.
        // Les trois gabarits relevés ensemble : un mode sombre forcé l'est souvent partout.
        $forces = [];

        foreach (['app', 'client-company', 'provider-company'] as $layout) {
            $contenu = $this->codeDe(resource_path("views/layouts/{$layout}.blade.php"));

            if (preg_match('/<html[^>]*\bclass="[^"]*\bdark\b/', $contenu) === 1) {
                $forces[] = "layouts/{$layout}";
            }
        }

        $this->assertSame([], $forces, 'Ces gabarits forcent le mode sombre : le thème doit suivre la préférence du compte.');
    }

    public function test_un_texte_blanc_repose_toujours_sur_un_fond_sature(): void
    {
        // LE DÉFAUT QUE LA CONVERSION A FAILLI LAISSER.
        $sature = '/bg-(sky|blue|emerald|green|red|rose|indigo|violet|purple|amber|orange|slate)-(500|600|700|800|900)\b/';

        $coupables = [];

        foreach ($this->fichiers() as $fichier) {
            $contenu = $this->codeDe($fichier);

            preg_match_all('/class="[^"]*"/', $contenu, $attributs);

            foreach ($attributs[0] as $attribut) {
                // `dark:text-white` est LÉGITIME : c'est la variante mode sombre, appariée à un `text-slate-900` pour le mode clair.
                if (preg_match('/(?<![a-z:])text-white/', $attribut) && ! preg_match($sature, $attribut)) {
                    $coupables[basename($fichier)][] = trim($attribut);
                }
            }
        }

        $this->assertSame(
            [],
            $coupables,
            "Texte blanc sans fond saturé — il sera illisible sur une surface claire :\n"
                .json_encode($coupables, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }

    public function test_les_tableaux_de_bord_emploient_l_en_tete_de_l_admin(): void
    {
        // Un en-tete PARTAGE, pas recopie : `ui-page-title` ou la coquille de page.
        $tableauxDeBord = [
            'views/livewire/admin-dashboard.blade.php' => 'cockpit-hero',
            'views/livewire/provider-company/provider-dashboard.blade.php' => 'x-page-shell',
            'views/livewire/client-company/client-company-dashboard.blade.php' => 'x-page-shell',
        ];

        $isolees = [];

        foreach ($tableauxDeBord as $vue => $marqueur) {
            if (! str_contains((string) file_get_contents(resource_path($vue)), $marqueur)) {
                $isolees[] = $vue;
            }
        }

        $this->assertSame([], $isolees, 'Ces vues n’emploient pas l’en-tête de page commun.');
    }

    /**
     * Les règles qui repeignent le texte en clair épargnent les surfaces restées CLAIRES,
     * et les deux feuilles portent EXACTEMENT la même réserve — deux listes divergeraient.
     */
    public function test_les_regles_de_repeinture_portent_toutes_la_meme_reserve(): void
    {
        $regles = [
            'css/base.css' => ['body.cx-shell :is(h1, h2, h3, h4)', '.dark :is(h1, h2, h3, h4)'],
            'css/vitrine-mode.css' => [
                'body.cx-shell :is(.text-slate-900, .text-slate-800)',
                ':is(.text-slate-700, .text-slate-600, .text-slate-500, .text-slate-400)',
            ],
        ];

        $manques = [];
        $reserves = [];

        foreach ($regles as $feuille => $selecteurs) {
            $css = (string) file_get_contents(resource_path($feuille));

            foreach ($selecteurs as $regle) {
                $debut = strpos($css, $regle);

                if ($debut === false) {
                    $manques[] = "{$feuille} : la règle `{$regle}` a disparu";

                    continue;
                }

                // Le sélecteur va jusqu'à l'accolade ouvrante : les `:not()` sont dedans.
                $portee = substr($css, $debut, (int) strpos($css, '{', $debut) - $debut);
                $coupe = strpos($portee, ':not(');

                if ($coupe === false) {
                    $manques[] = "{$feuille} `{$regle}` : aucune réserve — repeint aussi les surfaces claires";

                    continue;
                }

                $reserves[trim(substr($portee, $coupe))][] = "{$feuille} `{$regle}`";
            }
        }

        // Une teinte Tailwind en -50 ou -100 sert de fond clair, quelle que soit sa couleur.
        foreach (array_keys($reserves) as $reserve) {
            foreach (['bg-white', '-50', '-100', '.brio-glass'] as $motif) {
                if (! str_contains($reserve, $motif)) {
                    $manques[] = "une réserve ne couvre pas `{$motif}`";
                }
            }
        }

        if (count($reserves) > 1) {
            foreach ($reserves as $reserve => $ou) {
                $manques[] = 'réserve divergente sur '.implode(', ', $ou).' : '.substr($reserve, 0, 70).'…';
            }
        }

        $this->assertSame(
            [],
            $manques,
            'Une règle de repeinture sans réserve peint du texte clair sur une surface claire.',
        );
    }
}
