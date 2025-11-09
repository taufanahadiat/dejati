# Use official PHP with Apache image (acts like LAMPP stack)
FROM php:8.2-apache

# Install necessary PHP extensions (commonly needed for MySQL apps)
RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable mysqli

# Enable Apache mod_rewrite (useful for many PHP frameworks)
RUN a2enmod rewrite

# Allow .htaccess overrides globally
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Copy application source code into container
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose Apache port
EXPOSE 80
