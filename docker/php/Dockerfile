FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    bash \
    curl \
    git \
    icu-dev \
    libpq-dev \
    libzip-dev \
    oniguruma-dev \
    unzip \
    zip \
    nodejs \
    npm \
  && docker-php-ext-install pdo_pgsql mbstring intl zip opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-codered.ini

RUN addgroup -g 1000 -S www && adduser -u 1000 -S www -G www

USER www

EXPOSE 9000

CMD ["php-fpm"]
