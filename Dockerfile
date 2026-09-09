ARG COMPOSER_IMAGE=composer:2
ARG PHP_FPM_IMAGE=php:8.4-fpm-bookworm
ARG PECL_REDIS_VERSION=6.3.0
ARG PGVECTOR_VERSION=0.8.1
ARG PGVECTOR_SHA256=a9094dfb85ccdde3cbb295f1086d4c71a20db1d26bf1d6c39f07a7d164033eb4

FROM ${COMPOSER_IMAGE} AS vendor

WORKDIR /app

ARG COMPOSER_PACKAGIST_MIRROR=
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    COMPOSER_PROCESS_TIMEOUT=2000

RUN if [ -n "${COMPOSER_PACKAGIST_MIRROR}" ]; then \
        composer config -g repo.packagist composer "${COMPOSER_PACKAGIST_MIRROR}"; \
    fi

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --ignore-platform-req=ext-pcntl \
    --classmap-authoritative \
    --no-scripts

COPY . .

RUN composer dump-autoload \
    --no-dev \
    --no-interaction \
    --classmap-authoritative \
    --no-scripts \
 && BROADCAST_CONNECTION=null php artisan package:discover --ansi

FROM node:22-bookworm-slim AS assets

WORKDIR /app

RUN npm config set registry https://registry.npmmirror.com/

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./

RUN npm run build

FROM ${PHP_FPM_IMAGE}

ARG PECL_REDIS_VERSION
ARG PGVECTOR_VERSION
ARG PGVECTOR_SHA256

ENV APP_NAME=GEOFlow \
    APP_ENV=production \
    APP_DEBUG=false \
    APP_URL=http://localhost:8080 \
    APP_TIMEZONE=Asia/Shanghai \
    APP_LOCALE=zh_CN \
    APP_FALLBACK_LOCALE=en \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=pgsql \
    DB_HOST=127.0.0.1 \
    DB_PORT=5432 \
    DB_DATABASE=geo_flow \
    DB_USERNAME=geo_user \
    SESSION_DRIVER=database \
    QUEUE_CONNECTION=redis \
    CACHE_STORE=redis \
    CACHE_LIMITER_STORE=database \
    REDIS_CLIENT=phpredis \
    REDIS_HOST=127.0.0.1 \
    REDIS_PORT=6379 \
    BROADCAST_CONNECTION=reverb \
    REVERB_APP_ID=geoflow-reverb-app \
    REVERB_APP_KEY=geoflow-reverb-key \
    REVERB_BROADCAST_HOST=127.0.0.1 \
    REVERB_BROADCAST_PORT=18080 \
    REVERB_BROADCAST_SCHEME=http \
    REVERB_SERVER_HOST=127.0.0.1 \
    REVERB_SERVER_PORT=18080 \
    REVERB_SERVER_PATH=/reverb \
    FILESYSTEM_DISK=local \
    GEOFLOW_INITIAL_ADMIN_HINT_ENABLED=true \
    AUTO_MIGRATE=true \
    AUTO_INSTALL=true \
    AUTO_OPTIMIZE=true

WORKDIR /var/www/html

RUN set -eux; \
    if [ -f /etc/apt/sources.list.d/debian.sources ]; then \
        sed -i 's|deb.debian.org|mirrors.aliyun.com|g' /etc/apt/sources.list.d/debian.sources; \
        sed -i 's|security.debian.org|mirrors.aliyun.com|g' /etc/apt/sources.list.d/debian.sources; \
    fi; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        build-essential \
        ca-certificates \
        curl \
        gosu \
        libonig-dev \
        libpq-dev \
        libsqlite3-dev \
        libzip-dev \
        nginx \
        postgresql \
        postgresql-client \
        postgresql-contrib \
        postgresql-server-dev-15 \
        redis-server \
        supervisor \
        unzip; \
    docker-php-ext-install \
        mbstring \
        opcache \
        pcntl \
        pdo_pgsql \
        pdo_sqlite \
        zip; \
    curl -fsSL "https://pecl.php.net/get/redis-${PECL_REDIS_VERSION}.tgz" -o /tmp/redis.tgz; \
    pecl install /tmp/redis.tgz; \
    docker-php-ext-enable redis; \
    rm -f /tmp/redis.tgz; \
    rm -rf /var/lib/apt/lists/*; \
    rm -f /etc/nginx/sites-enabled/default

RUN set -eux; \
    curl --fail --location \
        --retry 8 \
        --retry-delay 3 \
        --retry-all-errors \
        --connect-timeout 30 \
        "https://codeload.github.com/pgvector/pgvector/tar.gz/refs/tags/v${PGVECTOR_VERSION}" \
        -o /tmp/pgvector.tar.gz; \
    echo "${PGVECTOR_SHA256}  /tmp/pgvector.tar.gz" | sha256sum -c -; \
    mkdir -p /tmp/pgvector; \
    tar -xzf /tmp/pgvector.tar.gz --strip-components=1 -C /tmp/pgvector; \
    make -C /tmp/pgvector; \
    make -C /tmp/pgvector install; \
    rm -rf /tmp/pgvector /tmp/pgvector.tar.gz

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/99-opcache.ini
COPY docker/php/php-docker-overrides.ini /usr/local/etc/php/conf.d/99-overrides.ini
COPY docker/php-fpm/www.conf /usr/local/etc/php-fpm.d/zz-geoflow.conf
COPY docker/baota/nginx.conf /etc/nginx/conf.d/geoflow.conf
COPY docker/baota/supervisord.conf /etc/supervisor/conf.d/geoflow.conf
COPY docker/baota/ai-workers.conf /etc/supervisor/conf.d/geoflow-ai-workers.conf
COPY docker/baota/redis.conf /etc/geoflow/redis.conf
COPY docker/baota/entrypoint.sh /usr/local/bin/geoflow-baota-entrypoint

COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

RUN chmod +x /usr/local/bin/geoflow-baota-entrypoint \
    && rm -rf /var/www/html/storage \
    && mkdir -p /data/storage /data/postgres /data/redis \
    && ln -s /data/storage /var/www/html/storage \
    && ln -s /data/.env /var/www/html/.env \
    && rm -f /var/www/html/public/storage \
    && ln -s ../storage/app/public /var/www/html/public/storage \
    && chown -R www-data:www-data /data/storage /var/www/html/bootstrap/cache \
    && chown -R postgres:postgres /data/postgres \
    && chown -R redis:redis /data/redis

VOLUME ["/data"]
EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=5 \
    CMD curl -fsS http://127.0.0.1:8080/up >/dev/null || exit 1

ENTRYPOINT ["/usr/local/bin/geoflow-baota-entrypoint"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
