# Оптимизированный Dockerfile для Laravel сервиса
# Используем многоэтапную сборку для лучшего кеширования

# Этап 1: Базовый образ с системными зависимостями
FROM php:8.2-fpm as base

# Устанавливаем системные зависимости (кешируется между сборками)
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    zip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    netcat-openbsd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Устанавливаем PHP расширения (кешируется между сборками)
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    zip \
    exif \
    pcntl \
    bcmath \
    gd

# Этап 2: Установка Composer зависимостей
FROM base as composer-deps

# Копируем Composer из официального образа
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Копируем только файлы зависимостей для лучшего кеширования
COPY composer.json composer.lock* ./

# Устанавливаем зависимости (этот слой будет кешироваться если composer файлы не изменились)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --ansi \
    --no-interaction

# Этап 3: Финальный образ
FROM base as final

# Копируем Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Устанавливаем рабочую директорию
WORKDIR /var/www/html

# Копируем установленные зависимости из предыдущего этапа
COPY --from=composer-deps /var/www/html/vendor ./vendor/

# Копируем исходный код (изменяется чаще всего, поэтому в конце)
COPY . .

# Финализируем установку Composer (генерируем автозагрузчик)
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    && composer clear-cache

# Создаем директории Laravel и устанавливаем права доступа
RUN mkdir -p storage/framework/views \
    && mkdir -p storage/framework/cache \
    && mkdir -p storage/framework/sessions \
    && mkdir -p storage/logs \
    && mkdir -p bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache \
    && chmod +x /var/www/html/entrypoint.sh

# Экспортируем порт
EXPOSE 9000

# Точка входа
ENTRYPOINT ["sh", "/var/www/html/entrypoint.sh"]
