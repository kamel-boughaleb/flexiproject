FROM php:8.4-fpm AS base

ARG UID
ARG GID
ARG CONTAINER_USER

RUN groupadd --force -g $GID $CONTAINER_USER
RUN useradd -ms /bin/bash --no-user-group -g $GID -u $UID $CONTAINER_USER

# GD a besoin de JPEG pour imagejpeg() : sans lui, les vignettes des cartes échouaient.
RUN apt-get update && apt-get install -y \
    git \
    curl \
    nginx \
    libpng-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    zip \
    unzip \
    libzip-dev \
    supervisor \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY docker/php-overrides.ini /usr/local/etc/php/conf.d/zz-app-overrides.ini

RUN sed -i "s/^user = .*/user = ${CONTAINER_USER}/" /usr/local/etc/php-fpm.d/www.conf \
    && sed -i "s/^group = .*/group = ${CONTAINER_USER}/" /usr/local/etc/php-fpm.d/www.conf \
    && sed -i "s|^listen = .*|listen = 127.0.0.1:9000|" /usr/local/etc/php-fpm.d/www.conf \
    && mkdir -p /usr/local/var/run \
    && chown -R ${CONTAINER_USER}:${CONTAINER_USER} /usr/local/var/run

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx-default.conf /etc/nginx/conf.d/default.conf
RUN mkdir -p /tmp/nginx/client_body_temp /tmp/nginx/proxy_temp /tmp/nginx/fastcgi_temp /tmp/nginx/uwsgi_temp /tmp/nginx/scgi_temp \
    && chown -R ${CONTAINER_USER}:${CONTAINER_USER} /tmp/nginx

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 8000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]


FROM base AS dev

RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

USER ${CONTAINER_USER}


FROM base AS vendor

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts


FROM node:22-slim AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
COPY --from=vendor /app/vendor/tightenco/ziggy vendor/tightenco/ziggy
RUN cp .env.example .env && npm run build


FROM base AS prod

COPY . .
COPY --from=vendor /app/vendor vendor
COPY --from=assets /app/public/build public/build

RUN mkdir -p storage bootstrap/cache \
    && chown -R ${CONTAINER_USER}:${CONTAINER_USER} storage bootstrap/cache

USER ${CONTAINER_USER}
