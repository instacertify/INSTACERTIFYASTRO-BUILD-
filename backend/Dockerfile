# Build context: repository root (needs ../content for seeding).
FROM composer:2 AS vendor
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

FROM dunglas/frankenphp:1-php8.3-alpine
RUN install-php-extensions pdo_pgsql redis intl zip gd bcmath pcntl opcache
# Production defaults (no error output to visitors), with room for the admin's 8 MB images and 5 MB CVs;
# GD needs ~150 MB to resize a large phone photo.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'upload_max_filesize=16M\npost_max_size=20M\nmemory_limit=512M\nexpose_php=Off\n' > "$PHP_INI_DIR/conf.d/zz-instacertify.ini"
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY backend/ ./
COPY content/ /content/
COPY --from=vendor /app/vendor ./vendor
RUN composer dump-autoload --optimize --no-dev \
    && php artisan filament:assets \
    && chown -R www-data:www-data storage bootstrap/cache

ENV CONTENT_PATH=/content \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

EXPOSE 80
CMD ["sh", "-c", "php artisan migrate --force && php artisan storage:link --force && php artisan optimize && frankenphp php-server --listen :80 --root public/"]
