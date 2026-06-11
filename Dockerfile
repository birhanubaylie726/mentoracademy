FROM php:8.2-apache

# 1. Install and enable the PDO MySQL drivers that PHP needs to talk to your database
RUN docker-php-ext-install pdo pdo_mysql

# 2. Enable Apache rewrite module for clean routing or .htaccess
RUN a2enmod rewrite

# 3. Copy all your files into Apache's default web directory
COPY . /var/www/html/

# 4. Adjust permissions so Apache can execute your scripts smoothly
RUN chown -R www-data:www-data /var/www/html/

EXPOSE 80
