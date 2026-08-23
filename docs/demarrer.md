# Démarrer

Cette page vous mène d'un dépôt cloné à une plateforme qui tourne, avec des données que vous
pouvez parcourir.

## Ce dont vous avez besoin

| Outil | Version | Vérifier |
|---|---|---|
| PHP | 8.5 | `php -v` |
| Composer | 2.x | `composer -V` |
| Node | 20+ | `node -v` |
| MySQL | 8+ | `mysql --version` |

Les extensions PHP requises sont déclarées dans `composer.json`. `composer install` refuse
d'aboutir s'il en manque une.

L'extension `intl` est **facultative mais recommandée**. Sans elle, le formatage des montants et
des dates passe par un repli maison qui produit un résultat correct mais moins fin.

## Installer

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Ouvrez `.env` et renseignez la base :

```dotenv
DB_CONNECTION=mysql
DB_DATABASE=brio
DB_USERNAME=root
DB_PASSWORD=
```

## Peupler la base

```bash
php artisan migrate:fresh --seed
```

Cette commande **efface tout** puis rejoue les 184 migrations et les semeurs. Comptez deux à
trois minutes.

Vous obtenez :

| | |
|---|---|
| 6 secteurs | Bâtiment & rénovation, Nettoyage, Espaces verts, Services à la personne, Sécurité, Mobilité |
| 16 métiers | tous rattachés à un secteur, tous tarifés |
| 6 zones de service | avec leur grille de prix |
| 110 traductions | les noms du catalogue en néerlandais, anglais, allemand, espagnol, italien |
| Des comptes de démonstration | client, prestataire, société, administrateur |

Les mots de passe des comptes semés sont dans `config/seeding.php`. Un test vérifie que les
harnais d'essai emploient la même valeur.

## Lancer

```bash
npm run build          # ou `npm run dev` pour recompiler à chaque modification
php artisan serve
```

La plateforme répond sur `http://127.0.0.1:8000`.

Pour le temps réel (chat, présence des prestataires, suivi de course), lancez aussi :

```bash
php artisan reverb:start
php artisan queue:work --queue=default,notifications
```

## Ce que vous devriez voir

| Adresse | Attendu |
|---|---|
| `/` | La page d'accueil, avec les secteurs du catalogue |
| `/services` | Les 16 métiers, chacun avec son icône |
| `/commander` | Le parcours de commande — secteur, puis métier, puis questions |
| `/admin` | La console d'administration (compte administrateur requis) |

Si `/services` affiche des mots comme « sparkles » ou « hammer » à la place des icônes, votre
cache de vues est périmé : `php artisan view:clear`.

## Résoudre un démarrage qui échoue

**`SQLSTATE[HY000] [1049] Unknown database`**
La base n'existe pas. Créez-la : `mysql -e "CREATE DATABASE brio"`.

**`Vite manifest not found`**
Les assets ne sont pas compilés. Lancez `npm run build`.

**`Class "config" does not exist` au démarrage du serveur**
Cache d'amorçage corrompu. `php artisan optimize:clear`.

**La page est blanche, aucune erreur**
Regardez `storage/logs/laravel.log`. Mettez `APP_DEBUG=true` dans `.env` le temps du diagnostic.

## Ensuite

- [Architecture](architecture.md) — comprendre ce que vous venez de lancer
- [Domaine](domaine.md) — le vocabulaire de la plateforme
- [Tests](tests.md) — vérifier que votre installation est saine
