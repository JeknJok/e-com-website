FROM composer:2@sha256:ca11f643d7791ebdd90a34fc20caf11380d434007ae539a9ea75cd9212e13dca AS composer
FROM php:8.3-apache@sha256:a800bbd2c0aa322702259ed475556b2cbc311076a8d3c4bba9bfe96838745472
RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold upgrade -y --no-install-recommends \
    && apt-get install -y --no-install-recommends libonig-dev libjpeg62-turbo-dev libpng-dev libwebp-dev unzip \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install mysqli mbstring gd \
    && a2enmod rewrite headers \
    && sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's/:80>/:8080>/; s/ combined$/ mae/' /etc/apache2/sites-available/000-default.conf \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --no-scripts --no-plugins --no-interaction --classmap-authoritative
COPY docker/apache.conf /etc/apache2/conf-enabled/zz-mae.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/mae.ini
COPY . /var/www/html/
RUN mkdir -p /var/lib/mae /var/lock/mae-media \
    && chown -R www-data:www-data /var/lib/mae /var/lock/mae-media /var/www/html/assets/uploads \
    && chmod 0700 /var/lib/mae /var/lock/mae-media \
    && chmod +x /var/www/html/docker/entrypoint.sh
ENV APACHE_RUN_DIR=/tmp/apache2 \
    APACHE_LOCK_DIR=/tmp/apache2-lock \
    APACHE_PID_FILE=/tmp/apache2/apache2.pid \
    RUNTIME_DIR=/var/lib/mae
USER www-data
EXPOSE 8080
ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
CMD ["apache2-foreground"]
