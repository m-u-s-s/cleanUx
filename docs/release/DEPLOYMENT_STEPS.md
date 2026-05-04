# Deployment steps — CleanUx

## Étapes de déploiement

1. Récupérer le code :

git pull origin main

2. Installer les dépendances :

composer install --no-dev --optimize-autoloader
npm install
npm run build

3. Vérifier le fichier .env :

APP_ENV=production
APP_DEBUG=false
APP_URL=https://votre-domaine.be

4. Nettoyer et optimiser Laravel :

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

5. Migrer la base de données :

php artisan migrate --force

6. Vérifier la santé applicative :

php artisan app:production-health-check
php artisan app:go-live-readiness-report
php artisan app:consolidation-final-check

7. Tester manuellement :

- connexion admin ;
- connexion client ;
- connexion employé ;
- création rendez-vous ;
- dashboard finance ;
- export PDF/CSV ;
- notifications ;
- litige client ;
- incident employé ;
- missions terrain.
