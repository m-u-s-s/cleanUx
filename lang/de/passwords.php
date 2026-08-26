<?php

/*
|--------------------------------------------------------------------------
| Meldungen zum Zurücksetzen des Passworts
|--------------------------------------------------------------------------
|
| Dieselbe Lücke wie in `auth.php`. Das sind die einzigen Antworten, die jemand erhält, der keinen
| Zugang mehr zu seinem Konto hat: sie in der Rückfallsprache anzuzeigen heißt, genau im falschen
| Moment die falsche Sprache zu sprechen.
|
| `user` bleibt im Konditional („falls diese Adresse existiert"): das Formular verrät nicht, ob das
| Konto besteht, sonst diente es dazu, eingetragene Adressen aufzuzählen.
*/

return [

    'reset' => 'Ihr Passwort wurde zurückgesetzt. Alle Ihre Sitzungen wurden abgemeldet.',
    'sent' => 'Falls diese Adresse zu einem Konto gehört, ist soeben ein Link zum Zurücksetzen unterwegs.',
    'throttled' => 'Warten Sie einen Moment, bevor Sie einen neuen Link anfordern.',
    'token' => 'Dieser Link zum Zurücksetzen ist nicht mehr gültig. Fordern Sie einen neuen an.',
    'user' => 'Falls diese Adresse zu einem Konto gehört, ist soeben ein Link zum Zurücksetzen unterwegs.',

];
