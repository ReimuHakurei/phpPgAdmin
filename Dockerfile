FROM php:8.1-apache

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && install-php-extensions pgsql

COPY . /var/www/html
COPY conf/config.inc.php-docker /var/www/html/conf/config.inc.php

# Example build command:
# docker build -t ivoras/phppgadmin:latest .

