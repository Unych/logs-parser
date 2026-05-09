FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
        git \
        bash \
        unzip \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        tini

RUN set -eux; \
    apk add --no-cache --virtual .build-deps \
        autoconf \
        gcc \
        g++ \
        make \
        linux-headers; \
    docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        bcmath \
        opcache \
        intl \
        zip; \
    pecl install redis-6.1.0; \
    docker-php-ext-enable redis; \
    apk del .build-deps; \
    rm -rf /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html

ENTRYPOINT ["/sbin/tini", "--"]
CMD ["php-fpm"]
