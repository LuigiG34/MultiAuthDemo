FROM php:8.2-apache



RUN apt update && apt install -y \
    git \
    curl \
    zip \
    unzip


RUN docker-php-ext-install pdo pdo_mysql
RUN docker-php-ext-enable pdo_mysql
RUN a2enmod rewrite

COPY apache.conf /etc/apache2/sites-available/000-default.conf

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

EXPOSE 80