<?php

/**
 * DIE ZEITREGEL, EINMAL GESCHRIEBEN — deutsches Gegenstück zu `lang/fr/pricing.php`.
 *
 * Gleiche Auflage wie im französischen Original: die ZAHLEN stehen NICHT in diesem Text. Sie
 * kommen über `HourlyRuleText` aus `config/order_engine.php`. Ein in einen Satz getipptes
 * „×1,30" überlebt jede Konfigurationsänderung — und genau so zeigt eine Plattform am Ende eine
 * Regel an, die sie nicht mehr anwendet.
 */
return [

    'hourly' => [

        'rule_short' => 'Sie wählen die gewünschte Stundenzahl und können sie jederzeit verlängern '
            .'— vor oder während des Einsatzes — zum normalen Satz. Darüber hinaus geleistete '
            .'Stunden ohne Verlängerung werden nach :grace Minuten Karenz mit dem :multiplier-Fachen '
            .'des Stundensatzes berechnet.',

        'rule_full' => 'Diese Leistung wird nach aufgewendeter Zeit abgerechnet. Sie wählen die '
            .'Stundenzahl bei der Bestellung und können jederzeit verlängern — vor wie während des '
            .'Einsatzes — zum normalen Stundensatz; bezahlt werden nur die tatsächlich geleisteten '
            .'Stunden. Dauert der Einsatz ohne Verlängerung über die gekaufte Zeit hinaus, sind die '
            .'ersten :grace Minuten kostenlos; danach wird jede angefangene Viertelstunde mit dem '
            .':multiplier-Fachen des Stundensatzes berechnet. Dieser Aufschlag kommt zu bereits '
            .'angewandten Aufschlägen hinzu (Sofortbuchung, Nacht, Wochenende). Die abrechenbare '
            .'Mehrzeit kann die ursprünglich bestellte Dauer niemals überschreiten.',

        'rule_provider' => 'Stundenweise abgerechnete Leistungen werden für eine feste Dauer '
            .'verkauft, die der Kunde jederzeit verlängern kann. Über diese Dauer hinaus, und nach '
            .':grace Minuten Karenz, wird die Mehrzeit dem Kunden mit dem :multiplier-Fachen des '
            .'Stundensatzes berechnet. Sie werden für diese Zeit zu Ihrem normalen Satz vergütet; '
            .'der Aufschlag geht an die Plattform. Sagen Sie dem Kunden Bescheid, bevor die gekaufte '
            .'Zeit abläuft: er kann ohne Aufschlag verlängern, und das nützt Ihnen beiden.',

        'remaining' => 'Verbleibende Zeit',
        'overrun' => 'Überschrittene Zeit',
        'grace_running' => 'Ende der Karenzzeit',
        'purchased' => 'Gebuchte Zeit',
        'extend' => 'Verlängern',
        'extend_hint' => 'Zum normalen Satz, ohne Aufschlag.',
        'extended_notice' => 'Zeit verlängert. Bezahlt werden nur die tatsächlich geleisteten Stunden.',
        'overtime_line' => 'Mehrzeit — :minutes Min. zum erhöhten Satz',
        'capped_notice' => 'Die abrechenbare Mehrzeit hat ihre Obergrenze erreicht: die bestellte Dauer.',
    ],
];
