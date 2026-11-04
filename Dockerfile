# Use official PHP 8.2 with Apache
FROM php:8.2-apache

# Install MySQL extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN a2enmod rewrite

# Copy Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy your website files to the Apache folder
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html/
WORKDIR /var/www/html/

# Install PHPMailer via Composer
RUN composer require phpmailer/phpmailer

# Expose port 80
EXPOSE 80