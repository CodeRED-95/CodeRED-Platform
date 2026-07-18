FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    bash \
    curl \
    git \
    icu-dev \
    libpq-dev \
    libzip-dev \
    oniguruma-dev \
    su-exec \
    unzip \
    zip \
    nodejs \
    npm \
  && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
  && pecl install redis \
  && docker-php-ext-enable redis \
  && docker-php-ext-install pdo_pgsql mbstring intl zip opcache \
  && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-codered.ini
COPY docker/php/fpm/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/entrypoint.sh /usr/local/bin/codered-entrypoint

RUN chmod +x /usr/local/bin/codered-entrypoint \
    && addgroup -g 1000 -S www \
    && adduser -u 1000 -S www -G www \
    && mkdir -p \
        /var/www/html/bootstrap/cache \
        /var/www/html/storage/framework/cache \
        /var/www/html/storage/framework/sessions \
        /var/www/html/storage/framework/views \
        /var/www/html/storage/logs \
    && touch /var/www/html/storage/logs/laravel.log \
    && chown -R www:www /var/www/html/bootstrap/cache /var/www/html/storage \
    && find /var/www/html/bootstrap/cache /var/www/html/storage -type d -exec chmod 775 {} \; \
    && find /var/www/html/bootstrap/cache /var/www/html/storage -type f -exec chmod 664 {} \;

ENTRYPOINT ["codered-entrypoint"]

EXPOSE 9000

CMD ["php-fpm", "-F"]
