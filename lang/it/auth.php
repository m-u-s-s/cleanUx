<?php

/*
|--------------------------------------------------------------------------
| Messaggi di autenticazione
|--------------------------------------------------------------------------
|
| Questo file mancava mentre `it` risultava fra le lingue attive. Una password errata mostrava quindi
| la lingua di ripiego — il francese — su una pagina per il resto in italiano. Tre chiavi, e sono
| esattamente le tre frasi che legge chi non riesce a entrare.
|
| `failed` resta VOLUTAMENTE vago: dire che un indirizzo non esiste rivelerebbe a uno sconosciuto
| quali account esistono.
*/

return [

    'failed' => 'Queste credenziali non corrispondono a nessun account.',
    'password' => 'La password non è corretta.',
    'throttle' => 'Troppi tentativi di accesso. Riprovi fra :seconds secondi.',

];
