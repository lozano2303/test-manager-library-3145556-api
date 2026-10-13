FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock* ./

# Install PHP dependencies (including pgsql driver)
RUN composer require doctrine/dbal --no-scripts --no-autoloader
RUN composer install --no-scripts --no-autoloader --no-dev

# Copy application code
COPY . .

# Complete composer setup
RUN composer dump-autoload --optimize

# Create storage and cache directories with proper permissions
RUN mkdir -p storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/framework/views \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Copy environment file
RUN cp .env.example .env

# Generate application key
RUN php artisan key:generate

# Expose port 8000
EXPOSE 8000

# Start PHP built-in server
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]