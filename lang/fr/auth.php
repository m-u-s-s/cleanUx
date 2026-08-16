<?php

/*
|--------------------------------------------------------------------------
| Messages d'authentification
|--------------------------------------------------------------------------
|
| Ce fichier n'existait pas : un mot de passe erroné affichait « auth.failed » à l'écran, sous un
| titre resté en anglais. Trois clés, et ce sont les trois phrases que lit quiconque n'arrive pas à
| se connecter.
|
| `failed` reste VOLONTAIREMENT vague sur ce qui a échoué : dire « cette adresse n'existe pas »
| révélerait à un inconnu quels comptes existent.
*/

return [

    'failed' => 'Ces identifiants ne correspondent à aucun compte.',
    'password' => 'Le mot de passe est incorrect.',
    'throttle' => 'Trop de tentatives de connexion. Réessayez dans :seconds secondes.',

];
