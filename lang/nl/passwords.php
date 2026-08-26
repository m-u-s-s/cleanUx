<?php

/*
|--------------------------------------------------------------------------
| Berichten voor het opnieuw instellen van het wachtwoord
|--------------------------------------------------------------------------
|
| Dezelfde leemte als in `auth.php`. Dit zijn de enige antwoorden die iemand krijgt die geen
| toegang meer heeft tot zijn account: ze in de terugvaltaal tonen is precies op het moment dat
| het misgaat de verkeerde taal spreken.
|
| `user` blijft in de voorwaardelijke vorm (« als dit adres bestaat »): het formulier zegt niet of
| het account bestaat, anders zou het dienen om ingeschreven adressen op te sommen.
*/

return [

    'reset' => 'Uw wachtwoord is opnieuw ingesteld. Al uw sessies zijn afgemeld.',
    'sent' => 'Als dit adres bij een account hoort, is er zojuist een herstellink verstuurd.',
    'throttled' => 'Wacht even voordat u een nieuwe link aanvraagt.',
    'token' => 'Deze herstellink is niet meer geldig. Vraag een nieuwe aan.',
    'user' => 'Als dit adres bij een account hoort, is er zojuist een herstellink verstuurd.',

];
