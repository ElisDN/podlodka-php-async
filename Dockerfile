FROM php:8.4-cli-alpine3.21

RUN docker-php-ext-configure pcntl --enable-pcntl && docker-php-ext-install pcntl;

RUN mv $PHP_INI_DIR/php.ini-development $PHP_INI_DIR/php.ini

RUN addgroup -g 1000 app && adduser -u 1000 -G app -s /bin/sh -D app

WORKDIR /app

USER app
