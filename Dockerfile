FROM php:8.5-cli-alpine

RUN docker-php-ext-install bcmath

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dev paketlar ham o'rnatiladi: testlar shu image ichida ishga tushadi.
# composer fayllari alohida qatlamda: kod o'zgarsa paketlar qayta yuklanmaydi.
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --optimize --no-interaction

# Sozlamalar ENV dan keladi. Bo'sh .env faqat uni o'qiydigan kod ogohlantirish bermasligi uchun.
RUN mkdir -p /data \
    && touch /app/.env \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache /data

USER www-data

ENV APP_ENV=local \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/database.sqlite \
    SESSION_DRIVER=file \
    CACHE_STORE=file \
    QUEUE_CONNECTION=sync \
    LOG_CHANNEL=stderr \
    PHP_CLI_SERVER_WORKERS=4

VOLUME ["/data"]
EXPOSE 8000

ENTRYPOINT ["/app/docker/entrypoint.sh"]
# --no-reload: serve konteyner env o'zgaruvchilarini bola jarayonga uzatsin.
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]
