# Image de production EVENT TRANS (Laravel 13, PHP 8.3) : nginx + PHP-FPM, et la même image pour
# Horizon, le planificateur et Reverb (voir docker-compose.prod.yml). Aucune donnée secrète dans l'image :
# la configuration vient des variables d'environnement au démarrage.

# 1. Dépendances PHP (sans les outils de développement)
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# 2. Ressources front (Vite + Tailwind) ; Tailwind lit aussi les vues de pagination de Laravel
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY --from=vendor /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

# 3. Exécution
FROM php:8.3-fpm-alpine
RUN apk add --no-cache nginx supervisor postgresql17-client bash su-exec icu-libs libzip libpng libjpeg-turbo freetype \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev postgresql17-dev linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql intl zip gd bcmath pcntl opcache \
    && apk del .build-deps

COPY docker/production/php.ini /usr/local/etc/php/conf.d/zz-eventtrans.ini
COPY docker/production/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/production/supervisord.conf /etc/supervisord.conf
COPY docker/production/entrypoint.sh /usr/local/bin/eventtrans-entrypoint

WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /app /var/www/html
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build

RUN chmod +x /usr/local/bin/eventtrans-entrypoint scripts/*.sh \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/backups bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8080
ENTRYPOINT ["eventtrans-entrypoint"]
CMD ["web"]
