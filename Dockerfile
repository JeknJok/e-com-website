FROM php:8.3-apache
RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install mysqli mbstring \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*
COPY docker/apache.conf /etc/apache2/conf-enabled/mae.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/mae.ini
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html/assets/uploads
