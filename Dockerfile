# Supermon-ng Simple Dockerfile
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    gd \
    zip \
    pdo \
    pdo_mysql \
    mysqli \
    opcache

# Configure PHP
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Configure Apache
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite headers expires
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Healthcheck for container health (checks web app)
HEALTHCHECK --interval=30s --timeout=10s --retries=3 CMD curl -f http://localhost/health.php || exit 1

WORKDIR /var/www/html

# Copy application code (but NOT user_files, which is mounted as a volume)
COPY *.php ./
COPY includes/ ./includes/
COPY css/ ./css/
COPY js/ ./js/
COPY templates/ ./templates/

# Set permissions (user_files will be mounted as a volume)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
