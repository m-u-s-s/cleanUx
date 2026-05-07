<?php

/**
 * Phase 9 — Traductions FR enrichies.
 *
 * À fusionner avec lang/fr/app.php existant qui ne contient que 'nav'.
 * Garde les clés existantes ('account', 'login', 'nav.*') et ajoute les nouvelles.
 *
 * Convention : on regroupe par "namespace" pour faciliter la lecture.
 */
return [

    // Auth
    'account'           => 'Compte',
    'login'             => 'Connexion',
    'register'          => 'Inscription',
    'logout'            => 'Déconnexion',
    'reserve'           => 'Réserver',
    'account_security'  => 'Compte & sécurité',
    'client_space'      => 'Espace client',
    'language'          => 'Langue',

    // Navigation principale (extension du nav existant)
    'nav' => [
        'home'        => 'Accueil',
        'new_booking' => 'Nouveau rendez-vous',
        'my_bookings' => 'Mes rendez-vous',
        'history'     => 'Historique',
        'claims'      => 'Litiges',
        'favorites'   => 'Favoris',
        'my_day'      => 'Ma journée',
        'missions'    => 'Mes missions',
        'incident'    => 'Incident',
        'pilotage'    => 'Pilotage',
        'calendar'    => 'Calendrier',
        'planning'    => 'Planning',
        'feedbacks'   => 'Feedbacks',
        'services'    => 'Services',
        'companies'   => 'Entreprises',
        'finance'     => 'Finance',
        'analytics'   => 'Analytics',
        'quality'     => 'Qualité',
        'audit'       => 'Audit',
        'premium'     => 'Premium',
        'tools'       => 'Outils',
        'request'     => 'Demande',
        'bookings'    => 'Rendez-vous',
        'recurring'   => 'Récurrences',
        'invoices'    => 'Factures',
        'team'        => 'Équipe',
        'profile'     => 'Profil',
        'settings'    => 'Paramètres',
    ],

    // Booking statuses
    'status' => [
        'pending'     => 'En attente',
        'confirmed'   => 'Confirmé',
        'on_route'    => 'En route',
        'on_site'     => 'Sur place',
        'completed'   => 'Terminé',
        'cancelled'   => 'Annulé',
        'refused'     => 'Refusé',
    ],

    // Actions communes
    'actions' => [
        'create'       => 'Créer',
        'edit'         => 'Modifier',
        'delete'       => 'Supprimer',
        'cancel'       => 'Annuler',
        'confirm'      => 'Confirmer',
        'save'         => 'Enregistrer',
        'send'         => 'Envoyer',
        'pause'        => 'Mettre en pause',
        'resume'       => 'Reprendre',
        'archive'      => 'Archiver',
        'export'       => 'Exporter',
        'download'     => 'Télécharger',
        'view'         => 'Voir',
        'view_details' => 'Voir les détails',
        'back'         => 'Retour',
        'next'         => 'Suivant',
        'previous'     => 'Précédent',
        'today'        => "Aujourd'hui",
        'reset'        => 'Réinitialiser',
        'apply'        => 'Appliquer',
        'search'       => 'Rechercher',
    ],

    // Messages génériques
    'messages' => [
        'invalid_locale'     => 'Langue non supportée.',
        'language_changed'   => 'Langue mise à jour.',
        'currency_changed'   => 'Devise mise à jour.',
        'success'            => 'Opération réussie.',
        'error'              => 'Une erreur est survenue.',
        'not_found'          => 'Élément introuvable.',
        'unauthorized'       => "Vous n'êtes pas autorisé.",
        'session_expired'    => 'Votre session a expiré.',
        'changes_saved'      => 'Modifications enregistrées.',
        'no_data'            => 'Aucune donnée à afficher.',
    ],

    // Calendrier
    'calendar' => [
        'month'       => 'Mois',
        'week'        => 'Semaine',
        'list'        => 'Liste',
        'no_events'   => "Aucun rendez-vous sur cette période.",
        'this_month'  => 'Ce mois-ci',
        'last_month'  => 'Mois précédent',
        'days_short'  => ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
    ],

    // Récurrences
    'recurrence' => [
        'frequency'        => 'Fréquence',
        'daily'            => 'Quotidien',
        'weekly'           => 'Hebdomadaire',
        'monthly'          => 'Mensuel',
        'every_n_days'     => 'Tous les :n jours',
        'every_n_weeks'    => 'Toutes les :n semaines',
        'every_n_months'   => 'Tous les :n mois',
        'starts_on'        => 'Démarre le',
        'ends_on'          => 'Termine le',
        'no_end'           => 'Sans fin prévue',
        'occurrences'      => ':n occurrences prévues',
        'active'           => 'Active',
        'paused'           => 'En pause',
        'cancelled'        => 'Annulée',
        'completed'        => 'Terminée',
    ],

    // Analytics
    'analytics' => [
        'dashboard'             => 'Tableau de bord',
        'revenue'               => "Chiffre d'affaires",
        'bookings'              => 'Rendez-vous',
        'completed'             => 'Terminés',
        'cancellation_rate'     => "Taux d'annulation",
        'satisfaction'          => 'Satisfaction',
        'satisfaction_avg'      => 'Satisfaction moyenne',
        'active_sites'          => 'Sites actifs',
        'top_services'          => 'Top services',
        'top_sites'             => 'Top sites',
        'monthly_revenue'       => 'Chiffre d\'affaires mensuel',
        'status_breakdown'      => 'Répartition par statut',
        'satisfaction_trend'    => 'Évolution de la satisfaction',
        'period'                => 'Période',
        'vs_previous'           => 'vs précédent',
        'no_reviews'            => 'Aucun avis',
        'no_data_period'        => 'Aucune donnée sur cette période.',
        'overdue_invoices'      => 'factures en retard',
        'pending_approvals'     => 'approbations en attente',
        'open_incidents'        => 'incidents ouverts',
        'bookings_at_risk'      => 'RDV à risque',
    ],

    // Périodes
    'period' => [
        'last_7d'      => '7 derniers jours',
        'last_30d'     => '30 derniers jours',
        'last_90d'     => '90 derniers jours',
        'this_month'   => 'Ce mois',
        'last_month'   => 'Mois précédent',
        'this_quarter' => 'Ce trimestre',
        'last_quarter' => 'Trimestre précédent',
        'ytd'          => 'Année en cours',
        'this_year'    => 'Année courante',
        'last_year'    => 'Année précédente',
        'custom'       => 'Personnalisé',
    ],

    // Chatbot
    'assistant' => [
        'title'              => 'Assistant CleanUx',
        'subtitle'           => 'Toujours disponible',
        'streaming_active'   => 'Streaming actif',
        'placeholder'        => 'Posez votre question...',
        'confirm_action'     => "Confirmer l'action ?",
        'rate_limit'         => 'Tu as atteint la limite de messages. Réessaye dans quelques minutes.',
        'streaming_error'    => 'Erreur de streaming.',
        'connection_lost'    => 'Connexion interrompue.',
    ],
];
