\# CleanUx — Restore Runbook



\## Objectif



Ce document explique comment restaurer CleanUx après un incident.



Exemples d’incident :



\- base de données corrompue ;

\- suppression accidentelle ;

\- mauvais déploiement ;

\- perte de fichiers storage ;

\- erreur serveur ;

\- migration problématique.



\## Règle importante



Avant toute restauration :



1\. mettre l’application en maintenance ;

2\. sauvegarder l’état actuel ;

3\. restaurer la base ;

4\. restaurer les fichiers ;

5\. vider les caches ;

6\. tester les accès ;

7\. désactiver le mode maintenance.



\## 1. Mettre CleanUx en maintenance



```bash

php artisan down

