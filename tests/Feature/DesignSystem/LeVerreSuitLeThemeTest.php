<?php

namespace Tests\Feature\DesignSystem;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DEUX DEFAUTS QUI RENDAIENT DU TEXTE ILLISIBLE EN MODE SOMBRE.
 *
 * 1. `premium.css` REDONNAIT une definition de `.brio-glass` au blanc EN DUR —
 *    `rgba(255,255,255,.7)` — et cette feuille est importee APRES `glass.css`. A specificite
 *    egale, c'est elle qui gagnait : la version par JETONS ne s'appliquait jamais, et le
 *    verre restait blanc sur la nuit. Mesure sur `/login` : le fond passe de
 *    `rgba(255,255,255,0.7)` a `rgba(255,255,255,0.055)` une fois le doublon retire.
 *
 * 2. LA RESERVE DES SURFACES CLAIRES MANQUAIT au chemin application. La transmutation du
 *    verre couvre les gris (`bg-white`, `bg-slate-50`) : ils deviennent sombres, et l'encre
 *    claire s'y lit. Elle ne couvre PAS les teintes d'accent — `bg-indigo-50`, `bg-blue-50`
 *    — qui restent CLAIRES. Leur texte devenait clair quand meme.
 *
 *    Mesure en PIXELS RENDUS, mode sombre, 390 px, sur `/commander` :
 *      avant  texte rgb(234,240,251) sur rgb(239,246,255)  ->   1,05:1
 *      apres  texte rgb(15,23,42)    sur rgb(239,246,255)  ->  16,40:1
 *
 *    Le chemin vitrine (`body.cx-shell`) avait cette reserve depuis le debut. C'est la meme
 *    lecon, appliquee a une seule des deux surfaces.
 */
class LeVerreSuitLeThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_verre_n_est_defini_qu_une_fois(): void
    {
        $premium = (string) file_get_contents(resource_path('css/premium.css'));

        // Le commentaire de tete CITE l'ancienne valeur pour dire ce qui a ete retire.
        $balisage = preg_replace('/\/\*.*?\*\//s', '', $premium) ?? '';

        $this->assertDoesNotMatchRegularExpression(
            '/\.brio-glass\s*\{/',
            $balisage,
            '`.brio-glass` vit dans `glass.css` : une seconde definition ici la recouvrirait.',
        );
    }

    /**
     * TEMOIN — la definition par JETONS existe toujours, et elle est bien la seule.
     *
     * Sans ce controle, supprimer les deux definitions passerait le test precedent en
     * laissant `.brio-glass` sans aucun style.
     */
    public function test_temoin_le_verre_reste_defini_par_ses_jetons(): void
    {
        $glass = (string) file_get_contents(resource_path('css/glass.css'));

        $this->assertMatchesRegularExpression(
            '/\.brio-glass,\s*\n\.brio-card,\s*\n\.ui-card \{[^}]*background-color: var\(--glass-bg\)/s',
            $glass,
        );
    }

    public function test_la_reserve_protege_les_surfaces_claires(): void
    {
        $glass = (string) file_get_contents(resource_path('css/glass.css'));

        // Les trois regles de texte doivent TOUTES porter la reserve : une seule oubliee
        // laisserait une famille de texte devenir claire sur une teinte restee claire.
        $this->assertSame(
            3,
            preg_match_all('/body:not\(\.cx-shell\) :is\(\.text-[^)]+\)[^{]*:not\(:is\(\.bg-indigo-50[^)]*\) \*\)/', $glass),
        );
    }

    /**
     * TEMOIN — la reserve couvre les teintes d'accent, PAS les gris.
     *
     * Les gris SONT transmutes en verre sombre : les reserver aussi laisserait leur texte
     * noir sur un fond devenu sombre — le defaut exactement inverse.
     */
    public function test_temoin_la_reserve_ne_couvre_pas_les_gris(): void
    {
        $glass = (string) file_get_contents(resource_path('css/glass.css'));

        preg_match('/:not\(:is\((\.bg-indigo-50[^)]*)\) \*\)/', $glass, $m);

        $this->assertNotEmpty($m, 'La reserve doit exister.');
        $this->assertStringContainsString('.bg-emerald-50', $m[1]);
        $this->assertStringNotContainsString('.bg-slate-50', $m[1]);
        $this->assertStringNotContainsString('.bg-white', $m[1]);
    }
}
