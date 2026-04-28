FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql

COPY api /var/www/html/api

EXPOSE 80
