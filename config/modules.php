<?php

/**
 * Registre des modules — source unique des points d'entrée du web.
 *
 * Il remplace quatre registres épars : les trois tableaux inline de `navigation-menu.blade.php`,
 * et les liens en dur des deux layouts société.
 *
 * `CatalogueDesModulesTest` part de la table de routes RÉELLE : toute page de tableau de bord
 * absente d'ici fait échouer la suite. C'est volontaire — ce dépôt a déjà produit des tests de
 * joignabilité qui asséraient une déclaration au lieu d'un chemin.
 *
 * Forme d'une entrée :
 *   key       string  identifiant stable
 *   label     string  libellé lu par l'utilisateur
 *   icon      string  emoji, traduit en Heroicon par `ModuleIcons`
 *   route     string  route nommée, cible de la case
 *   context   string  client | employe | admin | client-company | provider-company
 *   category  string  clé de `categories` ci-dessous
 *   primary   bool    true = reste dans la navbar allégée (5 par contexte au plus)
 */
return [
    'categories' => [
        'rendez-vous' => 'Rendez-vous & planning',
        'missions' => 'Missions & terrain',
        'documents' => 'Documents & contrats',
        'finance' => 'Finance & paiements',
        'comptes' => 'Comptes & organisations',
        'prestataires' => 'Prestataires & équipes',
        'communication' => 'Communication',
        'qualite' => 'Qualité & litiges',
        'conformite' => 'Conformité & sécurité',
        'croissance' => 'Croissance & fidélité',
        'donnees' => 'Données & analytics',
        'plateforme' => 'Plateforme & réglages',
    ],

    'catalogue' => [
        // Rempli en tâche 2, jusqu'à ce que CatalogueDesModulesTest passe.
    ],

    /*
     * Routes de tableau de bord qui ne sont PAS des modules : téléchargements et callbacks OAuth.
     * Elles n'ont pas de page, donc pas de case. Chaque ligne porte sa raison, pour qu'on ne
     * puisse pas y glisser un vrai module en douce.
     */
    'non_modules' => [
        'admin.export.csv' => 'Téléchargement CSV, pas une page',
        'admin.export.pdf' => 'Téléchargement PDF, pas une page',
        'admin.feedbacks.export' => 'Téléchargement, pas une page',
        'admin.feedbacks.export.csv' => 'Téléchargement CSV, pas une page',
        'admin.missions.export.pdf' => 'Téléchargement PDF, pas une page',
        'admin.quality.export.incidents.csv' => 'Téléchargement CSV, pas une page',
        'admin.quality.export.missions.csv' => 'Téléchargement CSV, pas une page',
        'client.analytics.export.bookings' => 'Téléchargement CSV, pas une page',
        'client.analytics.export.kpis' => 'Téléchargement CSV, pas une page',
        'client.analytics.export.monthly_revenue' => 'Téléchargement CSV, pas une page',
        'client.exports.bookings.xlsx' => 'Téléchargement XLSX, pas une page',
        'employe.stripe-connect.refresh' => 'Callback OAuth Stripe, pas une page',
        'employe.stripe-connect.return' => 'Callback OAuth Stripe, pas une page',
    ],
];
