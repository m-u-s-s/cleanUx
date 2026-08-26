<?php

/*
|--------------------------------------------------------------------------
| Messaggi di reimpostazione della password
|--------------------------------------------------------------------------
|
| La stessa lacuna di `auth.php`. Sono le uniche risposte che riceve chi ha perso l’accesso al
| proprio account: mostrarle nella lingua di ripiego significa parlare la lingua sbagliata proprio
| nel momento in cui qualcosa non va.
|
| `user` resta al condizionale («se questo indirizzo esiste»): il modulo non dice se l’account
| esiste, altrimenti servirebbe a elencare gli indirizzi registrati.
*/

return [

    'reset' => 'La sua password è stata reimpostata. Tutte le sue sessioni sono state disconnesse.',
    'sent' => 'Se questo indirizzo corrisponde a un account, un link di reimpostazione è appena partito.',
    'throttled' => 'Attenda un momento prima di richiedere un nuovo link.',
    'token' => 'Questo link di reimpostazione non è più valido. Ne richieda uno nuovo.',
    'user' => 'Se questo indirizzo corrisponde a un account, un link di reimpostazione è appena partito.',

];
