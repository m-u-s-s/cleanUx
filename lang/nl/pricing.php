<?php

/**
 * DE TIJDREGEL, ÉÉN KEER GESCHREVEN — Nederlandse versie van `lang/fr/pricing.php`.
 *
 * Dezelfde beperking als het Franse bestand: de cijfers staan er NIET voluit in, ze komen uit
 * `config/order_engine.php` via `HourlyRuleText`. Een « ×1,30 » die in een zin is getypt overleeft
 * een configuratiewijziging — en zo toont een platform uiteindelijk een regel die het niet meer
 * toepast.
 *
 * Dit bestand dekt dezelfde talen als `face_check.php`, en die verzameling is nu die welke in
 * `config/i18n.php` als ingeschakeld staat. De regel uit de vorige notitie blijft gelden — kondig
 * nooit een taal aan zonder de teksten erachter — maar wordt nu door een test afgedwongen in
 * plaats van door een opmerking: `UneLangueAnnonceeEstUneLangueTraduiteTest` weigert een
 * ingeschakelde taal waarvan de bestanden ontbreken, onvolledig zijn of een letterlijke kopie.
 */
return [

    'hourly' => [

        'rule_short' => 'U kiest zelf het aantal uren en kunt dit op elk moment verlengen, vóór of '
            .'tijdens de opdracht, tegen het normale tarief. Uren die daarbuiten worden gewerkt '
            .'zonder verlenging worden gefactureerd tegen :multiplier keer het uurtarief, na '
            .':grace minuten coulance.',

        'rule_full' => 'Deze dienst wordt afgerekend op basis van de bestede tijd. U kiest het aantal '
            .'uren bij de bestelling en kunt dit op elk moment verlengen — zowel vóór als tijdens de '
            .'opdracht — tegen het normale uurtarief; u betaalt uitsluitend de werkelijk gewerkte '
            .'uren. Loopt de opdracht uit buiten de gekochte tijd zonder dat u hebt verlengd, dan '
            .'zijn de eerste :grace minuten gratis; daarna wordt elk begonnen kwartier gefactureerd '
            .'tegen :multiplier keer het uurtarief. Deze toeslag komt bovenop eventueel reeds '
            .'toegepaste toeslagen (directe interventie, nacht, weekend). De factureerbare overschrijding '
            .'kan nooit meer bedragen dan de oorspronkelijk bestelde duur.',

        'rule_provider' => 'Diensten met uurtarief worden verkocht voor een vastgelegde duur, die de '
            .'klant op elk moment kan verlengen. Buiten die duur, en na :grace minuten coulance, '
            .'wordt de extra tijd aan de klant gefactureerd tegen :multiplier keer het uurtarief. U '
            .'wordt voor die tijd vergoed tegen uw normale tarief; de toeslag gaat naar het platform. '
            .'Waarschuw de klant vóór het einde van de gekochte tijd: hij kan zonder toeslag '
            .'verlengen, en dat is in zijn én in uw belang.',

        'remaining' => 'Resterende tijd',
        'overrun' => 'Tijd overschreden',
        'grace_running' => 'Einde van de coulance',
        'purchased' => 'Gereserveerde tijd',
        'extend' => 'Verlengen',
        'extend_hint' => 'Tegen het normale tarief, zonder toeslag.',
        'extended_notice' => 'Tijd verlengd. U betaalt uitsluitend de werkelijk gewerkte uren.',
        'overtime_line' => 'Overschrijding — :minutes min tegen verhoogd tarief',
        'capped_notice' => 'De factureerbare overschrijding heeft het maximum bereikt: de bestelde duur.',
    ],
];
