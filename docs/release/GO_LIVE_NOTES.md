# CleanUx — Go Live Notes

## Préproduction recommandée

Avant production, utiliser une préproduction avec :

- base de données proche de production
- mail sandbox
- Stripe test mode
- Google APIs désactivées ou sandboxées
- scheduler actif
- queue worker actif
- logs surveillés

## Variables importantes

À vérifier :

    APP_ENV=production
    APP_DEBUG=false
    APP_URL=https://...
    LOG_LEVEL=warning
    QUEUE_CONNECTION=database
    MAIL_MAILER=smtp

## Sécurité

Contrôles essentiels :

- Admin uniquement sur les pages admin
- Client uniquement sur ses propres données
- Employé uniquement sur ses missions
- Zone-scoped admin limité à ses zones
- Exports sensibles bloqués pour readonly admin
- Documents financiers protégés
- Feedbacks filtrés par propriétaire ou zone

## Backup

Avant production :

- backup base de données
- backup .env
- backup storage public si fichiers clients
- tag Git de release

Exemple :

    git tag v2.0.0-phase2
    git push origin v2.0.0-phase2

## Validation finale

    php artisan optimize:clear
    php artisan test
    git status
