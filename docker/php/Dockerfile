FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    bash \
    curl \
    git \
    icu-dev \
    libpq-dev \
    libzip-dev \
    oniguruma-dev \
    postgresql16-client \
    su-exec \
    unzip \
    zip \
    # zstd: compresión de cada lote del formato .rucbackup. Estaba
    # disponible de forma indirecta como dependencia de otro paquete, pero
    # sin declararlo aquí una actualización de la imagen base podría
    # dejarlo fuera y romper backup/restore. Ver docs-ruc/BACKUP_FORMAT.md.
    zstd \
    nodejs \
    npm \
  && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
  && pecl install redis \
  && docker-php-ext-enable redis \
  # pcntl: sin esta extension, Laravel no instala manejador de senales en
  # queue:work (Worker::supportsAsyncSignals() comprueba extension_loaded).
  # Como el worker corre ademas como PID 1, el kernel descarta el SIGTERM que
  # no tiene manejador y el contenedor no para hasta agotar stop_grace_period.
  # Queda desactivada en el pool de PHP-FPM: la web no necesita control de
  # procesos (ver docker/php/fpm/www.conf).
  && docker-php-ext-install pdo_pgsql mbstring intl zip opcache pcntl \
  && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-codered.ini
COPY docker/php/fpm/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/entrypoint.sh /usr/local/bin/codered-entrypoint

RUN chmod +x /usr/local/bin/codered-entrypoint \
    && addgroup -g 1000 -S www \
    && adduser -u 1000 -S www -G www \
    && git config --system --add safe.directory /var/www/html \
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
