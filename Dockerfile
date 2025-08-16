# Stage 1: Node.js build
FROM node:18 AS build

WORKDIR /app

# Copy package files
COPY package*.json ./
# Copy Vite config if it exists
COPY vite.config.js* ./
COPY vite.frontend.config.js* ./

# Install dependencies
RUN npm ci --only=production

# Copy source code
COPY . .

# Build the application (only if build script exists)
RUN if grep -q '"build"' package.json; then npm run build; else echo "No build script found, skipping build"; fi

# Stage 2: Production stage
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

# Enable .htaccess files
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Set working directory
WORKDIR /var/www/html

# Copy PHP files
COPY --from=build /app/*.php ./
COPY --from=build /app/includes/ ./includes/
COPY --from=build /app/user_files/ ./user_files/
COPY --from=build /app/api/ ./api/
COPY --from=build /app/css/ ./css/
COPY --from=build /app/js/ ./js/
COPY --from=build /app/templates/ ./templates/
COPY --from=build /app/src/ ./src/

# Copy configuration files
COPY composer.json ./
COPY composer.lock ./
COPY bootstrap.php ./
# Copy environment file (handle missing file gracefully)
COPY env.example* ./
RUN if [ -f env.example ]; then cp env.example .env; else echo "env.example not found, creating basic .env"; echo "APP_ENV=production" > .env; fi

# Install Composer dependencies
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/user_files

# Create health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health.php || exit 1

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
