\# CleanUx — Phase 3B Security / Environment Checklist



\## Objectif



Préparer CleanUx pour un environnement production sécurisé.



\## Règles importantes



\- Ne jamais publier le vrai fichier `.env`.

\- Ne jamais mettre de vrais mots de passe dans GitHub.

\- Ne jamais mettre `APP\_DEBUG=true` en production.

\- Ne jamais exposer les clés Stripe, Reverb, SMTP ou base de données.

\- Toujours utiliser HTTPS en production.

\- Toujours tester les rôles admin, employé et client après déploiement.



\## Variables obligatoires en production



```env

APP\_ENV=production

APP\_DEBUG=false

APP\_URL=https://ton-domaine.be

QUEUE\_CONNECTION=database

SESSION\_DRIVER=database

LOG\_LEVEL=warning

