FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git curl zip unzip libpng-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY . .

RUN chmod -R 775 storage bootstrap/cache

EXPOSE 10000

CMD php artisan config:cache && php artisan route:cache && php artisan serve --host=0.0.0.0 --port=10000
