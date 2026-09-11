##################################
# Stage 1: PHP base              #
##################################
FROM php:8.4-fpm-alpine AS php-base

RUN apk add --no-cache \
    $PHPIZE_DEPS \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    oniguruma-dev \
    libxml2-dev \
    libzip-dev \
    postgresql-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        xml \
        zip \
        opcache \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del $PHPIZE_DEPS

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

##################################
# Stage 2: Dependências do PHP   #
##################################
FROM php-base AS php-deps

COPY composer.json composer.lock ./

RUN composer install \
    --no-scripts \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader

##################################
# Stage 3: Build dos assets      #
##################################
FROM node:22-alpine AS frontend

ENV CI=true

WORKDIR /app

COPY package.json package-lock.json ./
COPY --from=php-deps /var/www/vendor ./vendor/

RUN npm ci

COPY resources/ ./resources/
COPY vite.config.js ./
COPY tailwind.config.js ./ 
COPY postcss.config.js ./
COPY public/ ./public/

RUN npm run build

##################################
# Stage 4: Produção              #
##################################
FROM php-base AS php-prod

WORKDIR /var/www

COPY . .

COPY --from=php-deps /var/www/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY docker/production/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

RUN chown -R www-data:www-data /var/www

USER www-data

EXPOSE 9000

CMD ["php-fpm"]