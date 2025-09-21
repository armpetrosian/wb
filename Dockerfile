FROM php:8.2-fpm

# Установка системных зависимостей
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    default-mysql-client \
    cron \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Установка Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Рабочая директория
WORKDIR /var/www/html

# Копирование файлов проекта
COPY . .

# Установка PHP-зависимостей (без запуска artisan при билде)
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Генерация оптимизаций Laravel (будет выполняться уже после запуска контейнера)
CMD ["php-fpm"]
