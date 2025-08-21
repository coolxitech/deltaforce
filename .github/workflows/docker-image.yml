# 使用官方 PHP 8 FPM 镜像作为基础
FROM php:8.2-fpm

# 设置工作目录
WORKDIR /var/www/html

# 安装系统依赖
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd zip pdo pdo_mysql

# 安装 Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 复制项目文件
COPY . .

# 安装项目依赖
RUN composer install --no-dev --optimize-autoloader

# 设置文件权限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage

# 暴露端口（ThinkPHP 内置服务器默认 8000）
EXPOSE 8000

# 启动 ThinkPHP 内置服务器
CMD ["php", "think", "run", "-H", "0.0.0.0", "-p", "8000"]
