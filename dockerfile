# Dockerfile
FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy composer files first (for better caching)
COPY composer.json composer.lock* ./

# Install dependencies (without application code first for better caching)
RUN composer install --no-scripts --no-autoloader --prefer-dist || true

# Copy application code
COPY . .

# Complete composer install
RUN composer dump-autoload --optimize && \
    composer run-script post-install-cmd || true

# Create necessary directories and set permissions
RUN mkdir -p var/cache var/log public && \
    chown -R www-data:www-data /var/www && \
    chmod -R 775 /var/www/var

# Expose port 9000 for PHP-FPM
EXPOSE 9000

CMD ["php-fpm"]