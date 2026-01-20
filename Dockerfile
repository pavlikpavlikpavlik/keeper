FROM shinsenter/symfony:php8.4-apache

# Устанавливаем системные пакеты и расширения PHP для PostgreSQL
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# Устанавливаем Doctrine и зависимости (если нет composer.json, то этот шаг пропускаем, но обычно он есть)
# Копируем composer.json и composer.lock (если есть) и устанавливаем зависимости
# COPY symfony/composer.* /var/www/html/
# RUN composer install --no-dev --optimize-autoloader

# Но лучше установить Doctrine уже внутри контейнера, зайдя в него и выполнив команду, либо через Dockerfile, если мы копируем код.

# Однако, если мы не хотим пересобирать образ при каждом изменении кода, то лучше установить Doctrine в процессе разработки внутри контейнера.

# В данном случае, мы просто установим расширения, а Doctrine установим позже с помощью composer.

# Также можно установить утилиты для работы с базой (опционально)
# RUN apt-get install -y postgresql-client

# Очистка кеша
RUN apt-get clean && rm -rf /var/lib/apt/lists/*
