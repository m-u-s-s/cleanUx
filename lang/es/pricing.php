<?php

/**
 * LA REGLA DEL TIEMPO, ESCRITA UNA SOLA VEZ — versión española de `lang/fr/pricing.php`.
 *
 * Misma exigencia que el original francés: las CIFRAS no se escriben aquí. Llegan desde
 * `config/order_engine.php` a través de `HourlyRuleText`. Un «×1,30» tecleado dentro de una frase
 * sobrevive a un cambio de configuración — y así es exactamente como una plataforma acaba
 * mostrando una regla que ya no aplica.
 */
return [

    'hourly' => [

        'rule_short' => 'Usted elige cuántas horas necesita y puede ampliarlas en cualquier momento '
            .'— antes o durante el servicio — a la tarifa normal. Las horas realizadas por encima sin '
            .'ampliación se facturan a :multiplier veces la tarifa horaria, tras :grace minutos de '
            .'cortesía.',

        'rule_full' => 'Este servicio se factura por tiempo empleado. Usted elige el número de horas '
            .'al hacer el pedido y puede ampliarlo en cualquier momento — antes o durante el servicio '
            .'— a la tarifa horaria normal; solo se cobran las horas realmente prestadas. Si el '
            .'servicio se prolonga más allá del tiempo comprado sin que usted lo haya ampliado, los '
            .'primeros :grace minutos son gratuitos y, a partir de ahí, cada cuarto de hora empezado '
            .'se factura a :multiplier veces la tarifa horaria. Este recargo se suma a los recargos ya '
            .'aplicados (servicio inmediato, noche, fin de semana). El exceso facturable nunca puede '
            .'superar la duración pedida inicialmente.',

        'rule_provider' => 'Los servicios facturados por horas se venden por una duración concreta, '
            .'que el cliente puede ampliar en cualquier momento. Más allá de esa duración, y pasados '
            .':grace minutos de cortesía, el tiempo adicional se factura al cliente a :multiplier '
            .'veces la tarifa horaria. Usted cobra ese tiempo a su tarifa normal; el recargo es para '
            .'la plataforma. Avise al cliente antes de que se agote el tiempo comprado: puede '
            .'ampliarlo sin recargo, y a ambos les conviene.',

        'remaining' => 'Tiempo restante',
        'overrun' => 'Tiempo excedido',
        'grace_running' => 'Fin del periodo de cortesía',
        'purchased' => 'Tiempo contratado',
        'extend' => 'Ampliar',
        'extend_hint' => 'A la tarifa normal, sin recargo.',
        'extended_notice' => 'Tiempo ampliado. Solo se cobran las horas realmente prestadas.',
        'overtime_line' => 'Exceso — :minutes min a tarifa incrementada',
        'capped_notice' => 'El exceso facturable ha alcanzado su tope: la duración pedida.',
    ],
];
