FROM php:8.5.12-apache

COPY public/ /var/www/html/

RUN mkdir -p /var/www/data \
    && chown -R www-data:www-data /var/www/data

ENV DATABASE_PATH=/var/www/data/app.db

EXPOSE 80
