<?php

/*
|--------------------------------------------------------------------------
| Proxies de confiance
|--------------------------------------------------------------------------
| Lu par App\Http\Middleware\TrustProxies — et aussi, en repli, par le
| middleware du framework qui interroge lui-même config('trustedproxy.proxies').
|
| TRUSTED_PROXIES accepte :
|   - "*"                            → n'importe quel proxy (Cloudflare, ALB…)
|   - "10.0.0.0/8,172.16.0.0/12"     → liste CIDR séparée par des virgules
|   - rien                           → aucun proxy de confiance
|
| ATTENTION à la valeur rendue quand rien n'est configuré : c'est null, PAS un
| tableau vide. Le middleware du framework n'accorde sa confiance automatique
| aux plateformes gérées (Laravel Cloud, Forge, Vapor) que si la valeur vaut
| exactement null ; un tableau vide couperait ce repli sans rien dire. La
| normalisation est faite ici pour que la nuance survive à `config:cache`.
|
| Pas de clé 'headers' ici : le framework ne la lit jamais. Les en-têtes de
| confiance se déclarent dans App\Http\Middleware\TrustProxies::$headers.
*/

$proxies = env('TRUSTED_PROXIES');

return [

    'proxies' => match (true) {
        $proxies === '*' => '*',
        (bool) $proxies => array_map('trim', explode(',', (string) $proxies)),
        default => null,
    },

];
