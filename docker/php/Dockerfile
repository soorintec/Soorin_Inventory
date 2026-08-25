FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
      git curl zip unzip icu-dev oniguruma-dev libzip-dev \
      freetype-dev libjpeg-turbo-dev libpng-dev mariadb-client bash tzdata \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) pdo_mysql mbstring intl zip bcmath gd opcache exif \
 && cp /usr/share/zoneinfo/Asia/Tehran /etc/localtime \
 && echo "Asia/Tehran" > /etc/timezone

RUN apk add --no-cache $PHPIZE_DEPS && pecl install redis && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN echo "memory_limit=512M"        >  /usr/local/etc/php/conf.d/app.ini \
 && echo "upload_max_filesize=64M"  >> /usr/local/etc/php/conf.d/app.ini \
 && echo "post_max_size=64M"        >> /usr/local/etc/php/conf.d/app.ini \
 && echo "max_execution_time=180"   >> /usr/local/etc/php/conf.d/app.ini \
 && echo "date.timezone=Asia/Tehran">> /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www
