FROM php:8.2-apache

# 1. Enable Apache rewrite module for clean routing or .htaccess
RUN a2enmod rewrite

# 2. Copy all your files into Apache's default web directory
COPY . /var/www/html/

# 3. Adjust permissions so Apache can execute your scripts smoothly
RUN chown -R www-data:www-data /var/www/html/

EXPOSE 80
