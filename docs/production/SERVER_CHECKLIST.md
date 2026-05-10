\# CleanUx — Server Checklist



\## Serveur



\- OS Linux recommandé

\- PHP 8.5 obligatoire en CLI et PHP-FPM

\- Composer installé

\- Node.js installé

\- Nginx ou Apache configuré

\- SSL actif

\- Base de données disponible

\- Accès SSH sécurisé



\## Laravel



\- .env configuré

\- APP\_DEBUG=false

\- APP\_ENV=production

\- APP\_KEY générée

\- Migrations exécutées

\- Caches Laravel générés

\- Storage link actif

\- Permissions storage correctes

\- Permissions bootstrap/cache correctes



\## Cron Laravel



Ajouter dans le cron serveur :



```bash

\* \* \* \* \* cd /path/to/CleanUx \&\& /usr/bin/php8.5 artisan schedule:run >> /dev/null 2>\&1

