<?php

namespace Tests\Feature\I18n;

use App\Services\Localization\Money;
use App\Support\International\DeviseParPays;
use Tests\TestCase;

/**
 * AFFICHER UNE DEVISE FAUSSE AVEC APLOMB EST PIRE QUE NE RIEN AFFICHER.
 *
 * `Money` portait sa PROPRE liste de cinq devises — EUR, USD, GBP, CHF, CAD — pendant que
 * `DeviseParPays` en déclarait soixante et une. Les cinquante-six autres ne tombaient pas en
 * erreur : `format()` les RÉÉCRIVAIT EN EUROS.
 *
 *     $money->format(100, 'MAD')   →   « 100,00 € »
 *
 * Le dirham marocain est la devise d'un marché que ce produit annonce. Un client marocain aurait
 * lu son montant en euros — sur un écran, et sur sa facture.
 *
 * Deux sources pour une même notion, dont l'une ignorait quatre-vingt-douze pour cent de l'autre.
 * `DeviseParPays` fait foi désormais, et une devise qu'elle ignore garde son code ISO.
 */
class LeFormatageMonetaireNeMentPasTest extends TestCase
{
    private function money(): Money
    {
        return app(Money::class);
    }

    /**
     * TÉMOIN — l'euro en français ne bouge pas d'un caractère.
     *
     * C'est la condition qui rendait la migration des vues sans risque : `Money::format()` doit
     * produire EXACTEMENT ce que produisait `number_format($x, 2, ',', ' ').' €'`, sinon deux cent
     * quarante-neuf affichages changeraient de forme en même temps.
     */
    public function test_temoin_l_euro_en_francais_est_inchange(): void
    {
        // Les quatre montants releves puis compares d'un coup : si le format changeait, on veut
        // savoir sur LESQUELS, pas seulement que le premier a bouge.
        $ecarts = [];

        foreach ([1234.56, 80.0, 0.0, 12.5] as $montant) {
            $attendu = number_format($montant, 2, ',', ' ').' €';
            $obtenu = $this->money()->format($montant, 'EUR', 'fr');

            if ($obtenu !== $attendu) {
                $ecarts[] = "{$montant} : attendu [{$attendu}], obtenu [{$obtenu}]";
            }
        }

        $this->assertSame([], $ecarts, 'Le format de l euro en francais a change.');
    }

    public function test_une_devise_hors_de_l_ancienne_liste_n_est_plus_rendue_en_euros(): void
    {
        $rendu = $this->money()->format(100.0, 'MAD', 'fr');

        $this->assertStringNotContainsString('€', $rendu, 'Un montant en dirhams s’affichait en euros.');
        $this->assertStringContainsString('MAD', $rendu);
    }

    /**
     * LES DÉCIMALES SUIVENT LA NORME, PAS UNE HABITUDE.
     *
     * Le yen n'a pas de sous-unité ; le dinar koweïtien en a mille. Formater les deux à deux
     * décimales est faux dans les deux sens.
     */
    public function test_les_decimales_suivent_la_devise(): void
    {
        $this->assertSame('1 000 ¥', $this->money()->format(1000.0, 'JPY', 'fr'));
        $this->assertSame('12,346 KWD', $this->money()->format(12.3456, 'KWD', 'fr'));
        $this->assertSame('5 000 XOF', $this->money()->format(5000.0, 'XOF', 'fr'));
    }

    /**
     * UNE DEVISE TOTALEMENT INCONNUE GARDE SON CODE.
     *
     * Le repli le plus sûr n'est pas la devise par défaut : c'est le code tel quel, qui ne ment
     * sur rien.
     */
    public function test_une_devise_inconnue_garde_son_code(): void
    {
        $this->assertSame('10,00 ZZZ', $this->money()->format(10.0, 'ZZZ', 'fr'));
    }

    /**
     * UNE SEULE SOURCE DE VÉRITÉ.
     *
     * Si `DeviseParPays` apprend une devise demain, `Money` doit la connaître le même jour — sans
     * quoi le décalage recommence, et il est silencieux.
     */
    public function test_money_couvre_exactement_les_devises_de_la_table_geographique(): void
    {
        $geographiques = DeviseParPays::devisesConnues();
        sort($geographiques);

        $formatables = array_keys(Money::devisesSupportees());
        sort($formatables);

        $this->assertSame($geographiques, $formatables);
        $this->assertGreaterThan(50, count($formatables), 'La table géographique ne couvre presque rien.');
    }

    /**
     * LE SYMBOLE N'EST JAMAIS AMBIGU.
     *
     * « kr » désigne cinq couronnes et « $ » une quinzaine de dollars : ces devises-là gardent leur
     * code ISO plutôt qu'un symbole qui se trompe une fois sur deux.
     */
    public function test_les_couronnes_gardent_leur_code_plutot_qu_un_symbole_ambigu(): void
    {
        $ambigus = [];

        foreach (['SEK', 'NOK', 'DKK', 'ISK'] as $code) {
            $symbole = $this->money()->symbol($code);

            if ($symbole !== $code) {
                $ambigus[] = "{$code} rend [{$symbole}]";
            }
        }

        $this->assertSame([], $ambigus, 'Ces couronnes doivent garder leur code ISO.');

        // Contrôle positif : les symboles sans ambiguïté sont bien servis.
        $this->assertSame('€', $this->money()->symbol('EUR'));
        $this->assertSame('£', $this->money()->symbol('GBP'));
    }
}
