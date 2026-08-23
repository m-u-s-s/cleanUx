# Brio

Place de marché multi-métiers. Un client commande une intervention — nettoyage, peinture,
toiture, garde d'enfants, déménagement, course — et la plateforme trouve, envoie et paie le
professionnel qui l'exécute.

Particuliers et sociétés des deux côtés : un particulier peut commander à une société, une
société à un indépendant, et réciproquement.

## Démarrer en cinq minutes

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed
npm run build
php artisan serve
```

Vous obtenez une base peuplée : 6 secteurs, 16 métiers, 6 zones, des comptes de démonstration.
La procédure complète est dans [Démarrer](docs/demarrer.md).

## Ce qu'il y a dans le dépôt

| | |
|---|---|
| Laravel 12 · PHP 8.5 · Livewire 3 | MySQL en production, SQLite pour les tests |
| 297 modèles · 496 services · 239 composants Livewire | 901 routes, dont 546 d'API |
| 184 migrations · 59 semeurs | 1 023 fichiers de test, 7 415 tests |
| Applications mobiles Expo / React Native | `mobile/client` et `mobile/provider` |

## Documentation

| Page | Ce qu'elle couvre |
|---|---|
| [Démarrer](docs/demarrer.md) | Installer, lancer, se connecter, comprendre ce que vous voyez |
| [Architecture](docs/architecture.md) | Les couches, ce qui décide quoi, les choix structurants |
| [Domaine](docs/domaine.md) | Secteurs, métiers, zones, réservations, missions — le vocabulaire |
| [Parcours](docs/parcours.md) | Du panier à la mission payée, étape par étape |
| [API](docs/api.md) | Authentification, conventions, surfaces client / prestataire / admin |
| [Données](docs/donnees.md) | Schéma, migrations, semeurs, pièges connus |
| [Tests](docs/tests.md) | Lancer la suite, écrire un test qui prouve quelque chose |
| [Exploitation](docs/exploitation.md) | Déployer, superviser, les files et l'ordonnanceur |
| [Conventions](docs/conventions.md) | Règles de code, commits, revue |

L'index complet est dans [docs/README.md](docs/README.md).

## Commandes usuelles

```bash
composer test:parallele     # la suite complète en ~13 min sur 16 processus
php artisan test tests/Feature/OrderEngine    # une suite ciblée
vendor/bin/pint             # style de code — porte dure de la CI
vendor/bin/phpstan analyse --memory-limit=2G  # analyse statique, niveau 6
```

## Licence

Propriétaire. Tous droits réservés.
