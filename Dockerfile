# Image de production pour Render (offre gratuite, 512 Mo de RAM).
FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libzip-dev unzip \
    && docker-php-ext-install pdo_mysql intl zip bcmath opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/ports.conf /etc/apache2/ports.conf
COPY docker/mpm.conf /etc/apache2/conf-enabled/zz-mpm.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html

# Dépendances d'abord : couche mise en cache tant que composer.lock ne change pas.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/start.sh /usr/local/bin/start
RUN chmod +x /usr/local/bin/start

# Render fournit $PORT (10000 par défaut).
ENV PORT=10000
EXPOSE 10000

CMD ["start"]
