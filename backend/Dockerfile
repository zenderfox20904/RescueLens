FROM php:8.4-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libzip-dev libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev libicu-dev default-mysql-client \
 && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql gd intl zip bcmath exif \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-autoloader
COPY . .
RUN composer dump-autoload --optimize && chmod +x docker/entrypoint.sh
EXPOSE 8000
ENTRYPOINT ["docker/entrypoint.sh"]
