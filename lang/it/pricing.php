<?php

/**
 * LA REGOLA DEL TEMPO, SCRITTA UNA SOLA VOLTA — versione italiana di `lang/fr/pricing.php`.
 *
 * Stesso vincolo dell’originale francese: le CIFRE non si scrivono qui. Arrivano da
 * `config/order_engine.php` attraverso `HourlyRuleText`. Un «×1,30» digitato dentro una frase
 * sopravvive a un cambio di configurazione — ed è esattamente così che una piattaforma finisce per
 * mostrare una regola che non applica più.
 */
return [

    'hourly' => [

        'rule_short' => 'Lei sceglie quante ore le servono e può prolungarle in qualsiasi momento '
            .'— prima o durante l’intervento — alla tariffa normale. Le ore svolte oltre quel limite '
            .'senza proroga sono fatturate a :multiplier volte la tariffa oraria, dopo :grace minuti '
            .'di tolleranza.',

        'rule_full' => 'Questo servizio è fatturato in base al tempo impiegato. Lei sceglie il numero '
            .'di ore al momento dell’ordine e può prolungarlo in qualsiasi momento — prima come '
            .'durante l’intervento — alla tariffa oraria normale; si pagano solo le ore effettivamente '
            .'svolte. Se l’intervento si protrae oltre il tempo acquistato senza che lei l’abbia '
            .'prolungato, i primi :grace minuti sono offerti, poi ogni quarto d’ora iniziato è '
            .'fatturato a :multiplier volte la tariffa oraria. Questa maggiorazione si somma a quelle '
            .'eventualmente già applicate (intervento immediato, notte, fine settimana). Lo '
            .'sforamento fatturabile non può mai superare la durata inizialmente ordinata.',

        'rule_provider' => 'I servizi fatturati a ore sono venduti per una durata precisa, che il '
            .'cliente può prolungare in qualsiasi momento. Oltre tale durata, e trascorsi :grace '
            .'minuti di tolleranza, il tempo aggiuntivo è fatturato al cliente a :multiplier volte la '
            .'tariffa oraria. Lei è retribuito alla sua tariffa normale per quel tempo; la '
            .'maggiorazione spetta alla piattaforma. Avvisi il cliente prima che scada il tempo '
            .'acquistato: può prolungarlo senza maggiorazione, e conviene a entrambi.',

        'remaining' => 'Tempo rimanente',
        'overrun' => 'Tempo superato',
        'grace_running' => 'Fine della tolleranza',
        'purchased' => 'Tempo acquistato',
        'extend' => 'Prolunga',
        'extend_hint' => 'Alla tariffa normale, senza maggiorazione.',
        'extended_notice' => 'Tempo prolungato. Si pagano solo le ore effettivamente svolte.',
        'overtime_line' => 'Sforamento — :minutes min a tariffa maggiorata',
        'capped_notice' => 'Lo sforamento fatturabile ha raggiunto il suo tetto: la durata ordinata.',
    ],
];
