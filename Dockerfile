FROM php:8.2-fpm

# Установка зависимостей и PHP-расширений
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip default-mysql-client \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Установка Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Рабочая директория
WORKDIR /var/www/html

# Копирование проекта и установка зависимостей
COPY . .
RUN composer install --no-dev --optimize-autoloader

# Запуск PHP-FPM
CMD ["php-fpm"]
