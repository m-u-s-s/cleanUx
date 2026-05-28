<?php

return [

    /*
    | Legitimate exceptions, keyed by model class. Each value is a list of either
    | a column name (suppresses any finding for that column) or 'rule:<rule_name>'
    | (suppresses all findings of that rule for the model). ALWAYS add a comment
    | explaining why an entry is here — the allowlist must not become a dumping ground.
    */
    'ignore' => [
        // App\Models\Example::class => ['legacy_col', 'rule:unsettable_not_null'],
    ],

    /*
    | Models excluded from the audit entirely (database views, unmanaged tables).
    */
    'ignore_models' => [
        // App\Models\SomeView::class,
    ],

];
