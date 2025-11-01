# Use PHP with Apache
FROM php:8.2-apache

# Enable necessary Apache modules
RUN a2enmod rewrite headers

# Copy app code into the web root
COPY . /var/www/html/

# Set the working directory
WORKDIR /var/www/html

# Allow .htaccess overrides in the default site
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Enable PDO MySQL (if needed)
RUN docker-php-ext-install pdo pdo_mysql

# RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

