FROM php:7.4-fpm-bullseye

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring zip pcntl

RUN pecl install redis && docker-php-ext-enable redis

RUN docker-php-ext-install opcache

COPY php.ini /usr/local/etc/php/php.ini
COPY www.conf /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/backend-dev

COPY entrypoints.sh /usr/local/bin/entrypoints.sh
RUN chmod +x /usr/local/bin/entrypoints.sh

ENTRYPOINT ["entrypoints.sh"]