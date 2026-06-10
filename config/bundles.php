<?php

return [
    // Nombre de prestataires sollicités par item lors de la demande de devis.
    'quote_request_top_n' => (int) env('BUNDLES_QUOTE_REQUEST_TOP_N', 5),

    // Durée de validité d'une demande de devis (heures) avant expiration/escalade.
    'quote_request_ttl_hours' => (int) env('BUNDLES_QUOTE_REQUEST_TTL_HOURS', 48),

    // Remise groupage appliquée quand un bundle de plusieurs items est accepté (%).
    'group_discount_percent' => (float) env('BUNDLES_GROUP_DISCOUNT_PERCENT', 10),
];
