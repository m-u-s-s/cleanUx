<?php

namespace Tests\Feature\I18n;

use Tests\TestCase;

/**
 * Un montant ne s'affiche pas avec un symbole ecrit a la main.
 *
 * La devise suit la POSITION du client — BE/FR en euros, MA en dirhams. Un symbole colle a un
 * `number_format()` montre donc un faux montant a la moitie du marche. 243 occurrences mesurees,
 * 119 remplacees ; celles qui restent portent une unite, une plage ou un arrondi, et demandent
 * chacune une decision.
 *
 * CE GARDE PORTE SUR LES FORMES, PAS SUR UN INVENTAIRE : il interdit celles qui ont un
 * remplacement direct, et laisse les autres tranquilles.
 */
class AucunSymboleMonetaireEcritAlaMainTest extends TestCase
{
    /** Hors du code source, pour ne pas dependre de l'encodage du fichier. */
    private const EURO = "\u{20ac}";

    /**
     * Forme interdite => ce qu'il faut ecrire a la place.
     *
     * @return array<string, string>
     */
    private function formes(): array
    {
        $e = preg_quote(self::EURO, '/');

        return [
            // `{{ number_format($x, 2, …) }} €`
            '/\}\}\s*'.$e.'/u' => '<x-money :amount="..." />',

            // `€ {{ number_format($x, 2, …) }}`
            '/'.$e.'\s*\{\{\s*number_format/u' => '<x-money :amount="..." />',

            // `'€ '.number_format($x, 2, …)`
            '/\''.$e.'\s*\'\s*\.\s*number_format/u' => 'locale_currency(...)',
        ];
    }

    /** @return list<string> */
    private function vues(): array
    {
        $iterateur = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        $vues = [];

        foreach ($iterateur as $fichier) {
            if ($fichier->isFile() && str_ends_with($fichier->getFilename(), '.blade.php')) {
                $vues[] = $fichier->getPathname();
            }
        }

        return $vues;
    }

    /**
     * TEMOIN — chaque expression reconnait la forme qu'elle decrit, et n'attrape pas un montant
     * deja correct. Sans lui, une expression cassee passerait au vert sur du vide.
     */
    public function test_temoin_les_formes_sont_reconnues_et_les_bonnes_epargnees(): void
    {
        $e = self::EURO;
        $formes = array_keys($this->formes());

        $reconnu = function (string $extrait) use ($formes): bool {
            foreach ($formes as $forme) {
                if (preg_match($forme, $extrait) === 1) {
                    return true;
                }
            }

            return false;
        };

        $manques = [];

        foreach ([
            'montant suivi du symbole' => '{{ number_format($x, 2) }} '.$e,
            'symbole avant le montant' => $e.' {{ number_format($x, 2) }}',
            'concatenation PHP' => "'".$e." '.number_format(\$x, 2)",
        ] as $nom => $extrait) {
            if (! $reconnu($extrait)) {
                $manques[] = "{$nom} : aucune expression ne le reconnait";
            }
        }

        foreach ([
            'le composant' => '<x-money :amount="$x" />',
            'le helper' => 'locale_currency($x)',
            'un symbole seul dans du texte' => 'Tarifs en '.$e.' hors taxes',
        ] as $nom => $extrait) {
            if ($reconnu($extrait)) {
                $manques[] = "{$nom} : attrape a tort";
            }
        }

        $this->assertSame([], $manques);
        $this->assertGreaterThan(400, count($this->vues()), 'Le balayage ne voit presque aucune vue.');
    }

    public function test_aucune_vue_ne_colle_un_symbole_a_un_montant(): void
    {
        $racine = str_replace(chr(92), '/', resource_path('views'));
        $fautives = [];

        foreach ($this->vues() as $chemin) {
            $relatif = ltrim(str_replace($racine, '', str_replace(chr(92), '/', $chemin)), '/');

            // Un courriel et un PDF ne rendent pas un composant Blade de mise en page.
            if (preg_match('#^(emails|mail|notifications|pdf|exports)/#', $relatif) === 1) {
                continue;
            }

            $code = (string) file_get_contents($chemin);

            foreach ($this->formes() as $forme => $remede) {
                if (preg_match_all($forme, $code, $m) > 0) {
                    $fautives[] = "{$relatif} : ".count($m[0]).' x — employez '.$remede;
                }
            }
        }

        $this->assertSame([], $fautives, 'Le symbole doit venir de la devise du montant, pas du gabarit.');
    }
}
