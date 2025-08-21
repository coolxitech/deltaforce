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

# 创建 www 用户和组
RUN groupadd -g 1000 www \
    && useradd -u 1000 -g www -s /bin/bash -m www

# 复制项目文件
COPY . .

# 安装项目依赖
RUN composer install --no-dev --optimize-autoloader

# 设置文件权限（检查 storage 目录是否存在）
RUN if [ -d "/var/www/html/storage" ]; then \
        chown -R www:www /var/www/html && \
        chmod -R 755 /var/www/html/storage; \
    else \
        echo "Storage directory not found, skipping chmod"; \
    fi

# 暴露端口（ThinkPHP 内置服务器默认 8000）
EXPOSE 8000

# 启动 ThinkPHP 内置服务器
CMD ["php", "think", "run", "-H", "0.0.0.0", "-p", "8000"]
