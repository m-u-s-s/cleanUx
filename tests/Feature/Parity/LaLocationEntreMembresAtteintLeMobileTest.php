<?php

namespace Tests\Feature\Parity;

use Tests\TestCase;

/**
 * LE REGISTRE WEB ET LE REGISTRE MOBILE DISENT LE MEME MODULE.
 *
 * `config/modules.php` decide ce que le web affiche ; `config/parity.php` decide ce que les
 * applications affichent. Rien ne les relie : un ecran neuf ajoute au premier reste INVISIBLE
 * dans le second, sans erreur ni test rouge. C'est exactement ce qui est arrive aux logements.
 *
 * Ce garde ne couvre que la location entre membres — le seul module dont les deux registres sont
 * aujourd'hui alignes. L'etendre a tout le catalogue le rendrait rouge sur des ecrans absents du
 * mobile pour d'autres raisons ; un garde vrai sur un perimetre nomme vaut mieux qu'un garde
 * general assorti d'une liste d'exceptions, qui n'est qu'une liste de taches deguisee.
 */
class LaLocationEntreMembresAtteintLeMobileTest extends TestCase
{
    /** Les cases `peer.*` du registre web ont toutes leur entree mobile. */
    public function test_chaque_ecran_de_la_location_entre_membres_est_annonce_au_mobile(): void
    {
        $manquants = [];

        foreach ($this->casesDuRegistreWeb() as $cle => $chemin) {
            if (! in_array($chemin, $this->cheminsDuRegistreMobile(), true)) {
                $manquants[] = $cle.' → '.$chemin;
            }
        }

        $this->assertSame([], $manquants, implode("\n", [
            'Ces écrans existent sur le web et sont invisibles dans les applications.',
            'Ajoutez-les à config/parity.php :',
            ...$manquants,
        ]));
    }

    /**
     * TEMOIN — le balayage lit vraiment quelque chose.
     *
     * Sans ce controle, une erreur de filtre rendrait une liste vide, et le test precedent
     * passerait au vert en ne mesurant rien.
     */
    public function test_temoin_le_balayage_trouve_bien_les_ecrans_du_module(): void
    {
        $this->assertGreaterThanOrEqual(5, count($this->casesDuRegistreWeb()));
        $this->assertGreaterThan(50, count($this->cheminsDuRegistreMobile()));
    }

    /** @return array<string, string> clé du registre => chemin */
    private function casesDuRegistreWeb(): array
    {
        $cases = [];

        foreach (config('modules.catalogue', []) as $module) {
            if (! str_starts_with((string) $module['route'], 'peer.')) {
                continue;
            }

            if (! in_array($module['context'], ['client', 'employe'], true)) {
                continue;
            }

            $cases[$module['key']] = (string) parse_url(route($module['route'], [], false), PHP_URL_PATH);
        }

        return $cases;
    }

    /** @return list<string> */
    private function cheminsDuRegistreMobile(): array
    {
        return array_map(
            fn (array $module): string => '/'.ltrim((string) $module['path'], '/'),
            config('parity.modules', []),
        );
    }
}
