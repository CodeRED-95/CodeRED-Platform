FROM php:8.3-cli-alpine

LABEL version="2.2.0" \
      description="RUC Tool CLI - importa el padron SUNAT a Postgres con esquema compatible con CodeRED-Platform" \
      maintainer="CodeRED Platform"

# Dependencias del sistema: postgresql16-client trae pg_dump/pg_restore versión 16,
# EXACTA a la del servidor Postgres 16 usado (evita el mismatch de "postgresql-client"
# genérico, que en Alpine resuelve a la última versión mayor disponible, ej. 18, e
# impide restaurar dumps --format=custom entre versiones incompatibles).
# postgresql-dev es necesaria para compilar pdo_pgsql.
RUN apk add --no-cache \
    bash \
    git \
    coreutils \
    postgresql16-client \
    postgresql-dev \
    oniguruma-dev

# Extensiones PHP: solo pdo_pgsql (la herramienta es Postgres-only) + mbstring (ISO-8859-1 -> UTF-8)
RUN docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_pgsql \
    mbstring

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy application
COPY . .

# Install dependencies (baked into the image; overridden por bind mount en dev,
# pero el entrypoint las reinstala automáticamente si vendor/ falta)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Create necessary directories
RUN mkdir -p /root/.ruc-tool/backups /root/.ruc-tool/logs && \
    chmod -R 755 /root/.ruc-tool

# Set environment
ENV HOME=/root

RUN chmod +x /app/docker-entrypoint.sh

ENTRYPOINT ["/app/docker-entrypoint.sh"]
CMD ["tail", "-f", "/dev/null"]
