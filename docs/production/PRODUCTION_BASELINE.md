\# CleanUx — Phase 3A Production Baseline



\## Objectif



Préparer CleanUx pour un environnement de production réel, stable, sécurisé et maintenable.



\## Statut actuel



\- Phase 2 clôturée

\- Tag release créé : v2.0.0-phase2

\- Tests locaux validés

\- GitHub Actions CI verte

\- Documentation technique disponible

\- Dashboards admin, client et employé stabilisés



\## Préparation serveur



À vérifier avant mise en production :



\- PHP 8.5 compatible avec `composer.json` et `composer.lock`

\- Composer installé

\- Node.js et npm installés

\- Base de données configurée

\- Cron Laravel configuré

\- Queue worker configuré

\- HTTPS activé

\- Sauvegardes activées

\- Logs surveillés

\- Variables .env sécurisées



\## Variables .env importantes



```env

APP\_ENV=production

APP\_DEBUG=false

APP\_URL=https://ton-domaine.be



LOG\_CHANNEL=stack

LOG\_LEVEL=warning



CACHE\_STORE=file

SESSION\_DRIVER=database

QUEUE\_CONNECTION=database



MAIL\_MAILER=smtp

FILESYSTEM\_DISK=local

