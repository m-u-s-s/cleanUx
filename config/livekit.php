<?php

/*
|--------------------------------------------------------------------------
| LiveKit — appels audio et vidéo dans un canal d'équipe
|--------------------------------------------------------------------------
|
| Les appels étaient un GREENFIELD TOTAL : `VideoCallService` était un squelette qui levait sur chaque
| méthode, et `MaskedCallService` (Twilio Proxy) est complet mais n'a jamais été câblé — il sert un
| autre besoin, masquer les numéros entre client et prestataire, et reste intact.
|
| Ici il s'agit d'autre chose : parler à un collègue depuis le chantier. La note vocale du lot 7
| couvre la consigne ; un appel couvre la question qui n'attend pas.
|
| SANS CLÉ, RIEN N'EST PROPOSÉ. Les identifiants ne sont pas de la configuration facultative : un
| jeton signé avec une clé vide serait rejeté par le serveur LiveKit, et l'application afficherait un
| bouton qui échoue. `CallService::estConfigure()` est consulté avant de rendre le geste possible.
*/

return [

    /** L'URL du serveur LiveKit — `wss://...`, celle que le client mobile ouvre. */
    'url' => env('LIVEKIT_URL'),

    'api_key' => env('LIVEKIT_API_KEY'),
    'api_secret' => env('LIVEKIT_API_SECRET'),

    /*
     * Durée de validité d'un jeton, en secondes.
     *
     * Court par nature : un jeton d'accès à une salle n'a pas à survivre à l'appel. Deux heures
     * couvrent large une conversation d'équipe, et bornent la fenêtre si le jeton fuit.
     */
    'token_ttl' => (int) env('LIVEKIT_TOKEN_TTL', 7200),

    /*
     * Combien de temps ça sonne avant d'être compté comme MANQUÉ.
     *
     * Sans cette borne, un appel que personne ne décroche resterait « en cours » indéfiniment : la
     * bannière d'appel entrant ne disparaîtrait jamais, et l'historique afficherait des
     * conversations qui n'ont pas eu lieu.
     */
    'ring_timeout_seconds' => (int) env('LIVEKIT_RING_TIMEOUT', 45),
];
