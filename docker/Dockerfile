FROM php:8.3-fpm-alpine

WORKDIR /app

# Runtime tools and shared libraries.
# The libs stay permanently: gd/zip/mbstring link against them at runtime,
# so removing them with the build deps would break extension loading.
RUN apk add --no-cache \
    git \
    unzip \
    zip \
    curl \
    fcgi \
    libpng \
    libjpeg-turbo \
    freetype \
    libzip \
    oniguruma \
    icu-libs

# Build deps are virtual so they can be dropped after compiling.
# linux-headers is required by ext/sockets (linux/sock_diag.h).
RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        linux-headers \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
        oniguruma-dev \
        icu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        pdo_mysql \
        pcntl \
        sockets \
        zip \
    && pecl install redis xdebug \
    && docker-php-ext-enable redis xdebug \
    && pecl clear-cache \
    && apk del .build-deps \
    && rm -rf /tmp/pear

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY docker/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zzz-app.conf

# php-fpm healthcheck via the fcgi package's cgi-fcgi client.
RUN printf '%s\n' \
    '#!/bin/sh' \
    'set -e' \
    'SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET \' \
    '  cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong' \
    > /usr/local/bin/php-fpm-healthcheck \
    && chmod +x /usr/local/bin/php-fpm-healthcheck

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
