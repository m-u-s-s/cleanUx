\# CleanUx — Phase 3D Backup Strategy



\## Objectif



Mettre en place une stratégie de sauvegarde pour protéger les données CleanUx en production.



Les éléments critiques à sauvegarder sont :



\- base de données ;

\- fichiers `storage/app` ;

\- fichiers publics uploadés ;

\- documents PDF générés ;

\- logs utiles en cas d’incident ;

\- fichier `.env` production, mais jamais dans GitHub.



\## Données à protéger



\### Base de données



La base de données contient les données essentielles :



\- utilisateurs ;

\- rôles ;

\- rendez-vous ;

\- missions ;

\- feedbacks ;

\- litiges ;

\- factures ;

\- devis ;

\- entreprises ;

\- zones ;

\- abonnements ;

\- paiements ;

\- logs fonctionnels ;

\- notifications.



Elle doit être sauvegardée au minimum une fois par jour.



\### Fichiers storage



Le dossier `storage/app` peut contenir :



\- fichiers uploadés ;

\- documents générés ;

\- exports ;

\- pièces jointes ;

\- rapports mission ;

\- fichiers temporaires utiles.



À sauvegarder aussi quotidiennement.



\### Fichier .env production



Le fichier `.env` contient les secrets :



\- accès base de données ;

\- clés Stripe ;

\- clés Reverb ;

\- SMTP ;

\- APP\_KEY.



Il ne doit jamais être commité dans Git.



Il doit être sauvegardé séparément dans un endroit sécurisé.



\## Fréquence recommandée



\### Minimum



\- 1 backup base de données par jour ;

\- 1 backup fichiers par jour ;

\- conservation 7 jours minimum.



\### Recommandé



\- backup DB quotidien ;

\- backup storage quotidien ;

\- backup complet hebdomadaire ;

\- conservation 30 jours ;

\- test de restauration une fois par mois.



\## Exemple backup MySQL



Commande Linux production :



```bash

mysqldump -u cleanux\_user -p cleanux\_production > backup\_cleanux\_$(date +%F).sql

