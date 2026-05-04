# Rollback plan — CleanUx

## Objectif

Ce document explique comment revenir à une version stable si une mise en production échoue.

## Voir les derniers commits

git log --oneline -10

## Revenir temporairement à un ancien commit

git checkout COMMIT_HASH

## Annuler proprement un commit

git revert COMMIT_HASH

## Rollback complet avec prudence

git reset --hard COMMIT_HASH
git push --force-with-lease

Attention : cette dernière commande modifie l'historique distant. À utiliser uniquement si tu es certain.

## Après rollback

php artisan optimize:clear
php artisan test
