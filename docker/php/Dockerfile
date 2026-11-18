FROM php:8.2-fpm-alpine

RUN apk add --no-cache postgresql-dev libxml2-dev \
    && docker-php-ext-install -j$(nproc) pdo_pgsql pgsql opcache xml \
    && apk del postgresql-dev libxml2-dev

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-requesty-prod.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-requesty-pool.conf

RUN mkdir -p /var/www/html/storage/ratelimit /var/www/html/storage/security \
    && chown -R www-data:www-data /var/www/html/storage

WORKDIR /var/www/html
USER www-data
