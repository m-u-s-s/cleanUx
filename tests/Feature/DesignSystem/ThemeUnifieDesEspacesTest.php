<?php

namespace Tests\Feature\DesignSystem;

use Tests\TestCase;

/**
 * TOUS LES ESPACES D'OUTIL PARTAGENT LE THÈME DE LA CONSOLE D'ADMINISTRATION.
 *
 * POURQUOI CE FICHIER EXISTE. L'espace prestataire portait un thème SOMBRE à lui seul —
 * `<html class="dark">` écrit en dur, `bg-slate-900`, accent ambre — et l'espace client-société un
 * accent violet. Une même marque avait donc trois apparences selon le rôle de qui regardait, et un
 * gérant passant de son espace au tableau de bord admin avait l'impression de changer
 * d'application.
 *
 * RIEN NE L'EMPÊCHAIT DE RECOMMENCER. Ces divergences ne venaient pas d'une décision mais de
 * l'écriture au fil de l'eau : chaque écran choisissait ses classes, et personne ne comparait. Un
 * test est le seul endroit où « c'est la même marque » peut être une contrainte plutôt qu'une
 * intention.
 *
 * CE QUI EST GARDÉ, ET CE QUI NE L'EST PAS. On vérifie l'absence des SURFACES sombres et des
 * accents étrangers, pas la présence d'une classe précise : figer `bg-white` sur chaque carte
 * transformerait le moindre ajustement en échec de test, et ce serait mérité — un garde-fou qui
 * interdit de travailler finit désactivé.
 */
class ThemeUnifieDesEspacesTest extends TestCase
{
    /**
     * Les répertoires de vues qui doivent suivre l'idiome admin.
     *
     * La vitrine (`layouts/guest`, design system `cx-*`) en est EXCLUE : elle est délibérément
     * sombre, c'est son sujet — « une marque, deux modes », cf. `resources/css/app.css`.
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

    /**
     * Le contenu d'une vue, COMMENTAIRES BLADE RETIRÉS.
     *
     * Un commentaire explique souvent ce qu'on vient de retirer — en le citant. Le premier jet de
     * ce test s'est déclenché sur sa propre documentation, qui contient les mots `class="dark"`
     * pour dire qu'ils ont disparu. Un garde-fou qui accuse la note expliquant le correctif
     * apprend à se faire ignorer.
     */
    private function codeDe(string $chemin): string
    {
        return preg_replace('/\{\{--.*?--\}\}/s', '', file_get_contents($chemin)) ?? '';
    }

    public function test_aucun_espace_d_outil_ne_repose_sur_une_surface_sombre(): void
    {
        /*
         * `bg-slate-700/800/900/950` en FOND : c'est ce qui faisait de l'espace prestataire une
         * application à part. Le texte `text-slate-900` reste évidemment permis — c'est la couleur
         * de titre de l'admin.
         *
         * Les variantes `dark:` sont tolérées : elles ne s'appliquent que si le compte a choisi le
         * mode sombre, ce que `layouts/app.blade.php` pilote pour TOUT le produit.
         */
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
        /*
         * L'accent est celui de `brio-btn-primary` : sky. L'ambre du prestataire et le violet du
         * client-société étaient des marques parallèles, nées d'aucune décision.
         *
         * `amber` reste permis en SÉMANTIQUE — un avertissement est ambre chez l'admin aussi. On
         * n'interdit donc que les usages d'ACCENT : fond plein et texte de marque.
         */
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
        /*
         * `layouts/provider-company` écrivait `<html … class="dark">` EN DUR. Cet espace ignorait
         * donc le sélecteur de thème que le reste du produit respecte : un compte réglé en clair
         * voyait quand même du sombre, et le réglage semblait cassé.
         *
         * `layouts/guest` est exclu — la vitrine est sombre par nature, et ne passe pas par `.dark`.
         */
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
        /*
         * LE DÉFAUT QUE LA CONVERSION A FAILLI LAISSER. Passer un espace du sombre au clair
         * retourne les fonds ; un `text-white` oublié devient alors du blanc sur blanc —
         * illisible, et invisible à la relecture parce que le texte est bien là dans le HTML.
         *
         * La règle se vérifie ATTRIBUT PAR ATTRIBUT : un fond saturé ailleurs dans le fichier ne
         * dit rien de celui sur lequel ce texte-ci se pose.
         */
        $sature = '/bg-(sky|blue|emerald|green|red|rose|indigo|violet|purple|amber|orange|slate)-(500|600|700|800|900)\b/';

        $coupables = [];

        foreach ($this->fichiers() as $fichier) {
            $contenu = $this->codeDe($fichier);

            preg_match_all('/class="[^"]*"/', $contenu, $attributs);

            foreach ($attributs[0] as $attribut) {
                /*
                 * `dark:text-white` est LÉGITIME : c'est la variante mode sombre, appariée à un
                 * `text-slate-900` pour le mode clair. Seul le `text-white` NU se pose sans
                 * condition, et c'est lui qui devient invisible sur une surface claire.
                 */
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
        /*
         * `ui-page-title` porte la taille, la graisse et l'espacement en UN endroit. Les recopier
         * en Tailwind brut — un `text-2xl font-black` ici, un autre là — est exactement ce qui
         * faisait dériver les espaces : un titre ajusté d'un côté ne suivait pas de l'autre.
         */
        $tableauxDeBord = [
            'views/livewire/admin-dashboard.blade.php' => 'cockpit-hero',
            'views/livewire/provider-company/provider-dashboard.blade.php' => 'ui-page-title',
            'views/livewire/client-company/client-company-dashboard.blade.php' => 'ui-page-title',
        ];

        $isolees = [];

        foreach ($tableauxDeBord as $vue => $marqueur) {
            if (! str_contains((string) file_get_contents(resource_path($vue)), $marqueur)) {
                $isolees[] = $vue;
            }
        }

        $this->assertSame([], $isolees, 'Ces vues n’emploient pas l’en-tête de page commun.');
    }
}
