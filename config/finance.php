<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Durée de validité des URLs signées de téléchargement PDF (minutes)
    |--------------------------------------------------------------------------
    | Les liens vers les devis/factures sont des URLs signées expirantes : un
    | lien partagé ou fuité cesse d'être valable passé ce délai (l'ownership
    | reste vérifié en plus).
    */
    'download_url_ttl_minutes' => (int) env('FINANCE_DOWNLOAD_URL_TTL_MINUTES', 30),

];
