<?php

/**
 * THE TIME RULE, WRITTEN ONCE — English counterpart of `lang/fr/pricing.php`.
 *
 * Same constraint as the French file: the figures are NOT spelled out here, they come from
 * `config/order_engine.php` through `HourlyRuleText`. A "×1.30" typed into a sentence survives a
 * configuration change — which is precisely how a platform ends up displaying a rule it no longer
 * applies.
 *
 * This file covers the same languages as `face_check.php`, and that set is now the one declared
 * enabled in `config/i18n.php`. The rule the earlier note stated still holds — never announce a
 * language without the strings behind it — but it is now enforced by a test rather than by a
 * comment: `UneLangueAnnonceeEstUneLangueTraduiteTest` refuses an enabled locale whose files are
 * missing, incomplete, or a byte-for-byte copy of another language.
 */
return [

    'hourly' => [

        'rule_short' => 'You choose how many hours you need, and you can extend at any time — before '
            .'or during the visit — at the normal rate. Hours worked beyond that without an extension '
            .'are billed at :multiplier times the hourly rate, after :grace minutes of grace.',

        'rule_full' => 'This service is billed by time spent. You choose the number of hours when '
            .'ordering and may extend at any time — before or during the visit — at the normal hourly '
            .'rate; you only pay for the hours actually worked. If the visit runs past the time you '
            .'bought without an extension, the first :grace minutes are free, then every quarter-hour '
            .'started is billed at :multiplier times the hourly rate. This surcharge is added to any '
            .'surcharges already applied (immediate booking, night, weekend). Billable overtime can '
            .'never exceed the duration originally ordered.',

        'rule_provider' => 'Hourly services are sold for a set duration, which the client may extend '
            .'at any time. Beyond that duration, and after :grace minutes of grace, the extra time is '
            .'billed to the client at :multiplier times the hourly rate. You are paid at your normal '
            .'rate for that time; the surcharge goes to the platform. Tell the client before the '
            .'purchased time runs out: they can extend without a surcharge, and that serves you both.',

        'remaining' => 'Time remaining',
        'overrun' => 'Time exceeded',
        'grace_running' => 'End of grace period',
        'purchased' => 'Time booked',
        'extend' => 'Extend',
        'extend_hint' => 'At the normal rate, no surcharge.',
        'extended_notice' => 'Time extended. You only pay for the hours actually worked.',
        'overtime_line' => 'Overtime — :minutes min at the increased rate',
        'capped_notice' => 'Billable overtime has reached its cap: the duration ordered.',
    ],
];
