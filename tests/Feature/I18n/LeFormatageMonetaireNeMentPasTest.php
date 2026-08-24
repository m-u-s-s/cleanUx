<?php

namespace Tests\Feature\I18n;

use App\Services\I18n\LocaleFormatter;
use App\Services\Localization\Money;
use App\Support\International\DeviseParPays;
use Tests\TestCase;

/** AFFICHER UNE DEVISE FAUSSE AVEC APLOMB EST PIRE QUE NE RIEN AFFICHER. */
class LeFormatageMonetaireNeMentPasTest extends TestCase
{
    private function money(): Money
    {
        return app(Money::class);
    }

    /** TÉMOIN — l'euro en français ne bouge pas d'un caractère. */
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

    /** LES DÉCIMALES SUIVENT LA NORME, PAS UNE HABITUDE. */
    public function test_les_decimales_suivent_la_devise(): void
    {
        $this->assertSame('1 000 ¥', $this->money()->format(1000.0, 'JPY', 'fr'));
        $this->assertSame('12,346 KWD', $this->money()->format(12.3456, 'KWD', 'fr'));
        $this->assertSame('5 000 XOF', $this->money()->format(5000.0, 'XOF', 'fr'));
    }

    /** UNE DEVISE TOTALEMENT INCONNUE GARDE SON CODE. */
    public function test_une_devise_inconnue_garde_son_code(): void
    {
        $this->assertSame('10,00 ZZZ', $this->money()->format(10.0, 'ZZZ', 'fr'));
    }

    /** UNE SEULE SOURCE DE VÉRITÉ. */
    public function test_money_couvre_exactement_les_devises_de_la_table_geographique(): void
    {
        $geographiques = DeviseParPays::devisesConnues();
        sort($geographiques);

        $formatables = array_keys(Money::devisesSupportees());
        sort($formatables);

        $this->assertSame($geographiques, $formatables);
        $this->assertGreaterThan(50, count($formatables), 'La table géographique ne couvre presque rien.');
    }

    /** LE SYMBOLE N'EST JAMAIS AMBIGU. */
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

    /**
     * LE RENDU NE DÉPEND D'AUCUNE BIBLIOTHÈQUE SYSTÈME.
     *
     * `intl` absente en local, présente sur la CI : le même montant s'affichait
     * `1 234,56 €` d'un côté et `1 234,56 €` de l'autre — une espace insécable étroite au lieu
     * d'une espace normale — et le yen passait de `¥` à `JPY`. Deux tests rouges, la CI bloquée
     * dix jours, et aucun déploiement possible derrière.
     */
    public function test_le_rendu_monetaire_ne_passe_par_aucun_formateur_du_systeme(): void
    {
        $sources = [
            'app/Services/Localization/Money.php',
            'app/Services/I18n/LocaleFormatter.php',
        ];

        $fautifs = [];

        foreach ($sources as $chemin) {
            $code = (string) file_get_contents(base_path($chemin));

            // On lit la méthode qui rend la monnaie, pas le fichier entier : `LocaleFormatter`
            // emploie encore ICU pour les DATES, ce qui est un autre sujet.
            $debut = strpos($code, 'function currency(') ?: strpos($code, 'function format(');

            if ($debut === false) {
                $fautifs[] = "{$chemin} : la méthode de rendu monétaire est introuvable";

                continue;
            }

            // Jusqu'à la méthode SUIVANTE : une fenêtre à taille fixe débordait sur `number()`,
            // qui emploie ICU pour les nombres — un autre sujet, légitime.
            $suite = strpos($code, '    public function', $debut + 10);
            $portee = $suite === false ? substr($code, $debut) : substr($code, $debut, $suite - $debut);

            foreach (['NumberFormatter', "extension_loaded('intl')"] as $motif) {
                if (str_contains($portee, $motif)) {
                    $fautifs[] = "{$chemin} : le rendu monétaire emploie `{$motif}`";
                }
            }
        }

        $this->assertSame(
            [],
            $fautifs,
            'Un montant doit s’afficher pareil sur toute machine : ICU varie avec sa version.',
        );
    }

    /**
     * Les deux services rendent le MÊME montant — `LocaleFormatter` forçait deux décimales,
     * et affichait le yen `1 000,00 JPY` là où `Money` rendait `1 000 ¥`.
     */
    public function test_les_deux_services_rendent_le_meme_montant(): void
    {
        $formatter = app(LocaleFormatter::class);
        $ecarts = [];

        foreach ([
            ['fr', 'EUR', 1234.5],
            ['en', 'EUR', 1234.5],
            ['de', 'EUR', 1234.5],
            ['nl', 'EUR', 1234.5],
            ['fr', 'JPY', 1000.0],
            ['fr', 'KWD', 12.3456],
            ['fr', 'MAD', 100.0],
        ] as [$locale, $devise, $montant]) {
            $parMoney = $this->money()->format($montant, $devise, $locale);
            $parFormatter = $formatter->currency($montant, $devise, $locale);

            if ($parMoney !== $parFormatter) {
                $ecarts[] = "{$locale}/{$devise} : Money rend [{$parMoney}], LocaleFormatter [{$parFormatter}]";
            }
        }

        $this->assertSame([], $ecarts, 'Deux rendus monétaires ont divergé.');
    }

    /** L'allemand groupe les milliers par le POINT, pas par l'espace. */
    public function test_l_allemand_groupe_les_milliers_par_le_point(): void
    {
        $this->assertSame('1.234,50 €', $this->money()->format(1234.5, 'EUR', 'de'));
    }
}
