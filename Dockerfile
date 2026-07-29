# syntax=docker/dockerfile:1

##
## Base: PHP 8.3-FPM with the extensions the application needs at runtime.
##
FROM php:8.3-fpm-bookworm AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        unzip \
        git \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        bcmath \
        zip \
        intl \
        opcache \
        gd \
        pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-usaharumahan.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/98-opcache.ini

WORKDIR /var/www/html

##
## Development: adds Composer + Node so the same image can run artisan,
## composer, npm, and the Vite dev server through docker compose.
##
FROM base AS development

RUN apt-get update && apt-get install -y --no-install-recommends \
        curl \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV PHP_INI_DIR_DEV=1

CMD ["php-fpm"]

##
## Build stage: installs PHP deps (no dev) and builds frontend assets for
## production images. Kept separate so the final runtime image stays lean.
##
FROM base AS build

RUN apt-get update && apt-get install -y --no-install-recommends curl \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

RUN composer dump-autoload --optimize --classmap-authoritative \
    && npm run build \
    && npm run build:ssr \
    && rm -rf node_modules

##
## Production runtime: only what's needed to serve the app.
##
FROM base AS production

ENV APP_ENV=production
ENV APP_DEBUG=false

COPY --from=build /var/www/html /var/www/html

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

USER www-data

CMD ["php-fpm"]
