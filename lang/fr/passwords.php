<?php

/*
|--------------------------------------------------------------------------
| Messages de réinitialisation de mot de passe
|--------------------------------------------------------------------------
|
| Même absence que `auth.php` : la page de mot de passe oublié annonçait « passwords.sent » et la
| réinitialisation réussie « passwords.reset ». Ce sont les seuls retours que reçoit quelqu'un qui a
| perdu l'accès à son compte.
|
| `user` reste au conditionnel (« si cette adresse existe ») : le formulaire ne dit pas si le compte
| existe, sans quoi il servirait à énumérer les adresses inscrites.
*/

return [

    'reset' => 'Votre mot de passe a été réinitialisé. Toutes vos sessions ont été déconnectées.',
    'sent' => 'Si cette adresse correspond à un compte, un lien de réinitialisation vient de partir.',
    'throttled' => 'Patientez avant de demander un nouveau lien.',
    'token' => "Ce lien de réinitialisation n'est plus valide. Demandez-en un nouveau.",
    'user' => 'Si cette adresse correspond à un compte, un lien de réinitialisation vient de partir.',

];
