FROM php:8.4-fpm

ARG APP_ENV=prod

RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libpq-dev \
    libssl-dev \
    libzip-dev \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        opcache \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

RUN mkdir -p var/cache var/log public/uploads \
    && chown -R www-data:www-data var/ public/uploads/ \
    && chmod -R 755 var/ public/uploads/

EXPOSE 9000

CMD ["php-fpm"]