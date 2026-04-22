# Google Calendar – mise en place

## 1. Paramètres à remplir dans le backoffice
Page : `/admin/integrations/google-agenda`

- Google Client ID
- Google Client Secret
- Redirect URI
- Calendar ID par défaut (`primary` si besoin)
- Activer `Sync agenda` et `Google provider`

## 2. Redirect URI
Utiliser la route de callback du projet :

`/integrations/google-agenda/callback`

En production, la valeur complète doit ressembler à :

`https://votre-domaine.be/integrations/google-agenda/callback`

## 3. Connexion utilisateur
- Admin : bouton sur la page Google Agenda
- Employé : page `/dashboard/employe/google-agenda`

## 4. Sync manuelle
Commande :

```bash
php artisan google-calendar:sync --future-days=30
```

## 5. Sync planifiée
Le scheduler lance automatiquement :

```bash
php artisan google-calendar:sync --future-days=30
```

toutes les 15 minutes.
