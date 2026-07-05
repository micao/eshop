FROM php:8.4-fpm

# 安装系统依赖
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 安装 PHP 核心扩展 (例如 MySQL 驱动, mbstring, gd 等)
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip sockets

# 安装并启用 Redis 扩展
RUN pecl install redis && docker-php-ext-enable redis

# 安装 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 设置工作目录
WORKDIR /var/www
