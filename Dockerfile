# syntax=docker/dockerfile:1

########################################
# Stage 1: PHP dependencies (composer)
########################################
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

########################################
# Stage 2: frontend assets (Vite build)
########################################
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
# Copiado aqui, antes do npm ci, de proposito: forca o BuildKit a esperar o
# estagio vendor terminar antes de comecar a instalar pacotes npm (ver
# comentario equivalente no estagio runtime, mesma razao). O conteudo em si
# so e usado mais abaixo, mas a dependencia de build precisa comecar aqui
# pra impedir vendor/assets de competirem por CPU/RAM em paralelo.
COPY --from=vendor /app/vendor/tightenco/ziggy ./vendor/tightenco/ziggy
RUN npm ci
COPY . .
RUN npm run build

########################################
# Stage 3: runtime image (php-fpm + nginx)
########################################
FROM php:8.3-fpm-alpine AS runtime

WORKDIR /var/www/html

# Copiado aqui, antes do apk add/gcc pesado abaixo, de proposito: forca o
# BuildKit a esperar os estagios vendor e assets terminarem antes de comecar
# a compilar extensoes PHP, completando a cadeia vendor -> assets -> runtime.
# Sem essa dependencia explicita, os tres estagios rodam em paralelo por
# padrao, competindo por CPU/RAM. Achado real (nao hipotetico): com o Docker
# Desktop local em 4 CPU/3.77GB, essa contencao (gcc compilando extensoes
# PHP ao mesmo tempo que npm baixa/extrai pacotes) dispara de forma
# intermitente um bug conhecido do npm ("Exit handler never called!") que
# deixa node_modules incompleto sem quebrar o layer (vite ausente depois).
# Comportamento final da imagem e identico — so a ORDEM de build muda; o
# .dockerignore ja exclui vendor/ e public/build do COPY . . mais abaixo,
# entao nao ha conflito de sobrescrita entre essas copias e aquela.
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

RUN apk add --no-cache \
        nginx \
        supervisor \
        postgresql-libs \
        icu-libs \
        libzip \
        libpng \
        libjpeg-turbo \
        freetype \
        oniguruma \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        postgresql-dev \
        icu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        mbstring \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/* /tmp/*

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

COPY --chown=www-data:www-data . .

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# bootstrap/cache/*.php e excluido do build context (.dockerignore) de proposito:
# um cache de descoberta de pacotes gerado no ambiente de dev referencia
# providers de pacotes require-dev (ex.: nunomaduro/collision), que nao
# existem no vendor/ instalado com --no-dev. Regera aqui, com o vendor de
# producao ja no lugar, pra nao ficar preso a um cache do host.
RUN php artisan package:discover --ansi

EXPOSE 8080

# Sem HEALTHCHECK aqui de proposito: esta mesma imagem roda como web, worker,
# scheduler e migration, e so o role "web" serve HTTP na 8080. Um HEALTHCHECK
# fixo nessa porta marcaria worker/scheduler/migration como UNHEALTHY pra
# sempre, e o ECS herdaria esse status Docker-nativo (nenhum task definition
# define healthCheck proprio), derrubando o service worker em loop. O health
# check do web e feito pelo target group do ALB, que sonda a rede
# diretamente e nao depende do HEALTHCHECK da imagem.

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["web"]
