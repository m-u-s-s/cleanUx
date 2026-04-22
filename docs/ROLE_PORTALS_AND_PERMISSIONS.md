# Rôles, portails et permissions

## 1. Rôles principaux
- `admin`
- `client`
- `employe`

Le projet garde encore certaines compatibilités historiques, mais le fonctionnement cible repose sur ces trois rôles.

## 2. Portail admin
Préfixe : `/admin`

Écrans principaux :
- dashboard
- calendrier
- planning
- missions
- utilisateurs
- feedbacks
- zones
- outils
- premium clients
- services
- entreprises
- finance
- analytics
- qualité
- audit logs
- emails
- modules
- pays

Permissions fines observées dans le projet :
- `manage-calendar`
- `manage-users`
- `manage-services`
- `manage-premium`
- `manage-entreprises`
- `manage-finance`
- `manage-analytics`
- `manage-quality`
- `manage-audit-logs`
- `manage-modules`

## 3. Portail client
Préfixe : `/dashboard/client`

Fonctions :
- dashboard
- création de rendez-vous
- mes rendez-vous
- édition de série récurrente
- historique
- litiges
- profil
- favoris employés
- documents finance

## 4. Portail employé
Préfixe : `/dashboard/employe`

Fonctions :
- dashboard
- planning
- missions
- feedbacks
- validation multiple
- Google Agenda
- disponibilités
- incident
- historique

## 5. Sécurité
Le projet s'appuie sur :
- middleware de rôle,
- middleware de compte actif,
- policies métier,
- gates par module,
- audit logs,
- tests d'accès route,
- tests de hardening.

## 6. Recommandations
- ne pas exposer une route admin sans gate ou policy adaptée,
- ne pas mélanger la logique de rôle avec du simple masquage UI,
- garder les exports sensibles derrière permissions explicites,
- vérifier qu'un admin scope zone ne voit que sa zone.
