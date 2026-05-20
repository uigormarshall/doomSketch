# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1 — Dependências PHP (Composer, sem dev)
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY . .
RUN composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
        --no-scripts

# ---------------------------------------------------------------------------
# Stage 2 — Build dos assets (Vite + Tailwind v4 + Flux)
# Flux importa de vendor/, então o vendor do stage anterior é necessário aqui.
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources resources
COPY public public
COPY --from=vendor /app/vendor vendor
RUN npm run build

# ---------------------------------------------------------------------------
# Stage 3 — Aplicação (PHP-FPM 8.4)
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-bookworm AS app

# Extensões PHP via instalador confiável
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
        pdo_mysql \
        mbstring \
        bcmath \
        gd \
        zip \
        exif \
        pcntl \
        intl \
        opcache

WORKDIR /var/www/html

# Tuning de PHP/OPcache
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

# Código da aplicação + vendor + assets compilados
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

# .env baked com APP_KEY única — fica APENAS na imagem, nunca no repositório.
# Como app/worker/scheduler usam a MESMA imagem, todos compartilham a mesma key.
RUN cp .env.example .env \
        && php artisan key:generate --force \
        && chown -R www-data:www-data storage bootstrap/cache \
        && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 9000
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# Stage 4 — Servidor web (nginx) servindo estáticos + proxy FastCGI ao php-fpm
# ---------------------------------------------------------------------------
FROM nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=assets /app/public /var/www/html/public
