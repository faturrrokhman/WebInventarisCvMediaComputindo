FROM php:8.2-apache

RUN docker-php-ext-install mysqli

WORKDIR /var/www/html

COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html/uploads