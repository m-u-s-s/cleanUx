# PHP 8.5 — la même version que la CI, que les flux de déploiement et que le serveur.
#
# POURQUOI CETTE LIGNE A CHANGÉ (M-14, 2026-08-12). L'image restait sur 8.3 alors que
# `composer.json` exige `php ^8.5` : le `composer install` d'en dessous refusait de résoudre
# les dépendances, et l'image ne se construisait tout simplement plus. Le même écart avait
# déjà cassé le déploiement le 2026-08-04, côté flux GitHub cette fois.
#
# VÉRIFIÉ AVANT LA MONTÉE :
#  - le tag `php:8.5-cli` existe sur Docker Hub (actif, dernière publication le 2026-08-05) ;
#  - les extensions compilées ci-dessous — pdo_mysql, zip, mbstring, exif, pcntl, bcmath, gd —
#    sont toutes livrées avec les sources de PHP 8.5 (branche PHP-8.5, répertoire ext/) ;
#  - phpredis, installé par PECL, est stable en 6.3.0 (publiée le 2025-11-06).
FROM php:8.5-cli

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql zip mbstring exif pcntl bcmath gd \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
