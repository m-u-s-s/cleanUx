<?php

namespace Tests\Feature\I18n;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * UNE LANGUE DECLAREE ACTIVE DOIT ETRE REELLEMENT TRADUITE.
 *
 * `config/i18n.php` declarait SIX langues actives. Trois d'entre elles — l'espagnol, l'italien,
 * l'allemand — n'avaient que deux fichiers sur quatre, et ces deux-la etaient l'ANGLAIS, octet
 * pour octet : meme objet git que `lang/en`. Les deux fichiers manquants tombaient sur la langue
 * de repli, le francais.
 *
 * Un utilisateur allemand voyait donc de l'anglais pour l'interface et du FRANCAIS pour les
 * tarifs et le controle facial. Rien ne pouvait le dire : Laravel ne signale pas une traduction
 * absente, il rend le repli — et une copie de l'anglais est un fichier parfaitement valide.
 *
 * TROIS QUESTIONS, PAS UNE. Les cles doivent exister, les substitutions doivent survivre, et le
 * fichier ne doit pas etre la copie d'une autre langue. La troisieme est la seule qui aurait
 * attrape le defaut d'origine.
 */
class UneLangueAnnonceeEstUneLangueTraduiteTest extends TestCase
{
    /** La langue qui fait reference : toute langue active doit porter les memes fichiers. */
    private const REFERENCE = 'fr';

    /**
     * Les fichiers qu'une langue active doit porter.
     *
     * `auth`, `passwords` et `validation` n'y sont PAS : ce sont les messages du cadre Laravel,
     * dont les traductions vivent dans `lang/vendor/` ou tombent sur le repli sans consequence
     * pour le sens. Ceux listes ici sont ecrits par le produit.
     *
     * @var list<string>
     */
    private const FICHIERS = ['app', 'ui', 'pricing', 'face_check'];

    /** @return list<string> */
    private function languesActives(): array
    {
        $actives = [];

        foreach ((array) Config::get('i18n.locales', []) as $code => $cfg) {
            if ($cfg['enabled'] ?? true) {
                $actives[] = (string) $code;
            }
        }

        return $actives;
    }

    public function test_chaque_langue_active_porte_tous_les_fichiers(): void
    {
        $manquants = [];

        foreach ($this->languesActives() as $langue) {
            foreach (self::FICHIERS as $fichier) {
                if (! is_file(lang_path("{$langue}/{$fichier}.php"))) {
                    $manquants[] = "{$langue}/{$fichier}.php";
                }
            }
        }

        sort($manquants);

        $this->assertSame([], $manquants,
            'Une langue est declaree active sans porter tous ses fichiers : elle tombera sur le repli, '
            .'et l\'utilisateur verra une AUTRE langue sans que rien ne le signale.');
    }

    public function test_chaque_langue_active_porte_toutes_les_cles_et_leurs_substitutions(): void
    {
        $ecarts = [];

        foreach (self::FICHIERS as $fichier) {
            $reference = $this->aplatir($this->charger(self::REFERENCE, $fichier));

            foreach ($this->languesActives() as $langue) {
                if ($langue === self::REFERENCE) {
                    continue;
                }

                $traduit = $this->aplatir($this->charger($langue, $fichier));

                foreach (array_diff(array_keys($reference), array_keys($traduit)) as $cle) {
                    $ecarts[] = "{$langue}/{$fichier} : cle absente — {$cle}";
                }

                foreach ($reference as $cle => $texte) {
                    if (! isset($traduit[$cle])) {
                        continue;
                    }

                    // Une substitution perdue affiche `:multiplier` a l'ecran, telle quelle.
                    if ($this->substitutions($texte) !== $this->substitutions($traduit[$cle])) {
                        $ecarts[] = "{$langue}/{$fichier} : substitutions — {$cle}";
                    }
                }
            }
        }

        sort($ecarts);

        $this->assertSame([], $ecarts, 'Une traduction a perdu une cle ou une substitution.');
    }

    public function test_aucune_langue_active_n_est_la_copie_d_une_autre(): void
    {
        $copies = [];

        foreach (self::FICHIERS as $fichier) {
            $empreintes = [];

            foreach ($this->languesActives() as $langue) {
                $chemin = lang_path("{$langue}/{$fichier}.php");

                if (! is_file($chemin)) {
                    continue;
                }

                // On compare le CONTENU TRADUIT, pas le fichier : deux en-tetes differents ne
                // font pas deux traductions.
                $empreinte = md5(serialize($this->aplatir($this->charger($langue, $fichier))));

                if (isset($empreintes[$empreinte])) {
                    $copies[] = "{$langue}/{$fichier} est la copie de {$empreintes[$empreinte]}/{$fichier}";
                }

                $empreintes[$empreinte] = $langue;
            }
        }

        sort($copies);

        $this->assertSame([], $copies,
            'Un fichier de langue est la copie exacte d\'une autre langue : la langue est annoncee, '
            .'elle n\'est pas traduite.');
    }

    /**
     * LE FICHIER JSON COMPTE AUTANT QUE LES FICHIERS PHP.
     *
     * `lang/xx.json` porte les chaines dont la CLE est la phrase francaise — celles qu'on ecrit
     * `__('Reserver')`. C'est le plus gros volume du produit : 374 entrees contre 354 pour les
     * quatre fichiers PHP reunis. `de.json`, `es.json` et `it.json` en portaient 364 sur 374
     * copiees mot pour mot de l'anglais.
     *
     * Le seuil n'est pas zero : « Premium », « GPS », « Standard », « Status » se disent pareil
     * dans plusieurs langues. Au-dela d'une poignee, c'est une copie, pas une coincidence.
     */
    public function test_aucun_fichier_json_n_est_une_copie_de_l_anglais(): void
    {
        $reference = $this->chargerJson('en');
        $copies = [];

        foreach ($this->languesActives() as $langue) {
            if (in_array($langue, ['en', self::REFERENCE], true)) {
                continue;
            }

            $traduit = $this->chargerJson($langue);
            $identiques = 0;

            foreach ($reference as $cle => $texte) {
                if (($traduit[$cle] ?? null) === $texte) {
                    $identiques++;
                }
            }

            if ($identiques > 25) {
                $copies[] = "{$langue}.json : {$identiques} chaines identiques a l'anglais sur "
                    .count($reference);
            }
        }

        sort($copies);

        $this->assertSame([], $copies,
            'Un fichier JSON de langue reprend l\'anglais : la langue est annoncee, elle n\'est pas traduite.');
    }

    public function test_chaque_json_porte_les_memes_cles_et_substitutions(): void
    {
        $reference = $this->chargerJson(self::REFERENCE);
        $ecarts = [];

        foreach ($this->languesActives() as $langue) {
            if ($langue === self::REFERENCE) {
                continue;
            }

            $traduit = $this->chargerJson($langue);

            foreach (array_diff(array_keys($reference), array_keys($traduit)) as $cle) {
                $ecarts[] = "{$langue}.json : cle absente — ".mb_substr((string) $cle, 0, 40);
            }

            foreach ($reference as $cle => $texte) {
                if (! isset($traduit[$cle])) {
                    continue;
                }

                if ($this->substitutions((string) $texte) !== $this->substitutions((string) $traduit[$cle])) {
                    $ecarts[] = "{$langue}.json : substitutions — ".mb_substr((string) $cle, 0, 40);
                }
            }
        }

        sort($ecarts);

        $this->assertSame([], $ecarts, 'Une traduction JSON a perdu une cle ou une substitution.');
    }

    /** @return array<string, string> */
    private function chargerJson(string $langue): array
    {
        $chemin = lang_path("{$langue}.json");

        if (! is_file($chemin)) {
            return [];
        }

        /** @var array<string, string> $decode */
        $decode = json_decode((string) file_get_contents($chemin), true) ?: [];

        return $decode;
    }

    /**
     * TEMOIN. Sans lui, les trois tests ci-dessus passeraient au vert si `languesActives()`
     * rendait une liste vide, ou si `aplatir()` ne trouvait plus rien.
     */
    public function test_temoin_la_mesure_mesure_encore_quelque_chose(): void
    {
        $actives = $this->languesActives();

        $this->assertGreaterThanOrEqual(4, count($actives),
            'La liste des langues actives s\'est videe : les tests ci-dessus ne prouveraient plus rien.');

        $this->assertContains('de', $actives);

        $reference = $this->aplatir($this->charger(self::REFERENCE, 'app'));

        $this->assertGreaterThan(100, count($reference),
            'L\'aplatissement ne trouve plus les cles : tout serait declare conforme.');

        // Le detecteur de substitutions reconnait bien ce qu'il cherche.
        $this->assertSame([':grace', ':multiplier'], $this->substitutions('a :multiplier fois apres :grace minutes'));
        $this->assertSame([], $this->substitutions('aucune substitution ici'));
    }

    /** @return array<string, mixed> */
    private function charger(string $langue, string $fichier): array
    {
        $chemin = lang_path("{$langue}/{$fichier}.php");

        return is_file($chemin) ? (array) require $chemin : [];
    }

    /**
     * @param  array<string, mixed>  $tableau
     * @return array<string, string>
     */
    private function aplatir(array $tableau, string $prefixe = ''): array
    {
        $plat = [];

        foreach ($tableau as $cle => $valeur) {
            $complete = $prefixe === '' ? (string) $cle : $prefixe.'.'.$cle;

            if (is_array($valeur)) {
                $plat += $this->aplatir($valeur, $complete);

                continue;
            }

            $plat[$complete] = (string) $valeur;
        }

        return $plat;
    }

    /** @return list<string> */
    private function substitutions(string $texte): array
    {
        preg_match_all('/:[a-z_]+/', $texte, $trouvees);

        $liste = array_values(array_unique($trouvees[0]));
        sort($liste);

        return $liste;
    }
}
