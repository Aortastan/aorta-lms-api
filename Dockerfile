FROM php:7.4-fpm-bullseye


RUN apt-get update && apt-get install -y ghostscript \
    git unzip libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql mbstring zip pcntl gd 


# install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN pecl install redis && docker-php-ext-enable redis

RUN docker-php-ext-install opcache

COPY php.ini /usr/local/etc/php/php.ini
COPY www.conf /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/backend

COPY entrypoints.sh /usr/local/bin/entrypoints.sh
RUN chmod +x /usr/local/bin/entrypoints.sh

ENTRYPOINT ["entrypoints.sh"]