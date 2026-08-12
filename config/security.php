<?php

use App\Http\Middleware\SecurityHeaders;

/*
|--------------------------------------------------------------------------
| En-têtes de sécurité HTTP
|--------------------------------------------------------------------------
| Lu par App\Http\Middleware\SecurityHeaders, qui tourne sur TOUTES les
| requêtes (middleware global du Kernel).
|
| Ces valeurs vivaient dans des appels env() faits depuis le middleware. Dès
| que `config:cache` tourne — le cas normal en production — env() rend null et
| les en-têtes se seraient vidés en silence. D'où ce fichier : c'est le seul
| endroit où env() reste lisible après mise en cache.
|
| Les valeurs par défaut ci-dessous sont EXACTEMENT celles qui étaient posées
| en second argument des anciens env(). Les changer change la sécurité rendue.
*/

return [

    // Défaut historique : 'SAMEORIGIN'. 'DENY' est plus strict et se pose par
    // .env quand aucune page n'a besoin d'être iframée par nous-mêmes.
    'x_frame_options' => env('SECURITY_X_FRAME_OPTIONS', 'SAMEORIGIN'),

    'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),

    'permissions_policy' => env(
        'SECURITY_PERMISSIONS_POLICY',
        'geolocation=(self), camera=(self), microphone=()'
    ),

    /*
    | AUCUNE valeur par défaut ici, et c'est délibéré.
    |
    | Le middleware n'applique la CSP de repli (clé suivante) que si CETTE
    | valeur-ci est vide ET qu'on est en production. Mettre la longue CSP en
    | défaut d'env() l'appliquerait d'un coup en local et en test, où le
    | rechargement à chaud de Vite et les inspecteurs seraient bloqués.
    */
    'csp' => env('SECURITY_CSP'),

    /*
    | Repli appliqué UNIQUEMENT en production, et UNIQUEMENT quand 'csp'
    | ci-dessus est vide. Non pilotable par env() exprès : pour la remplacer,
    | on renseigne SECURITY_CSP, ce qui la remplace entièrement.
    |
    | La chaîne elle-même vit dans le middleware, qui s'en sert aussi de plancher
    | si CE fichier venait à manquer. Une seule chaîne, donc une seule CSP
    | possible — la recopier ici en dur aurait fini par en produire deux.
    */
    'csp_production_fallback' => SecurityHeaders::CSP_PRODUCTION_PAR_DEFAUT,

];
