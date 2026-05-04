\# CleanUx — Phase 3C Queue / Cron / Scheduler



\## Objectif



Préparer CleanUx pour exécuter correctement les tâches automatiques en production :



\- jobs Laravel queue ;

\- scheduler Laravel ;

\- jobs failed ;

\- redémarrage des workers ;

\- surveillance des traitements automatiques.



\## Configuration recommandée en production



Dans le vrai fichier `.env` production :



```env

QUEUE\_CONNECTION=database

CACHE\_STORE=file

SESSION\_DRIVER=database

