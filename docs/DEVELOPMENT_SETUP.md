# Development Setup Guide

This guide shows how to set up Supermon-ng for local development without Docker.

## 🚀 Quick Development Setup

### Prerequisites
- PHP 8.2+
- MySQL 8.0+ or MariaDB 10.5+
- Redis 7.0+
- Node.js 18+
- Composer
- Git

### Step 1: Install Dependencies

#### On Ubuntu/Debian:
```bash
# Install PHP and extensions
sudo apt install php8.2 php8.2-mysql php8.2-redis php8.2-gd php8.2-zip php8.2-mbstring php8.2-xml php8.2-curl php8.2-cli

# Install MySQL
sudo apt install mysql-server

# Install Redis
sudo apt install redis-server

# Install Node.js
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install nodejs
```

#### On macOS:
```bash
# Using Homebrew
brew install php@8.2 mysql redis node

# Link PHP
brew link php@8.2
```

#### On Windows:
- Install XAMPP or WAMP
- Install Node.js from https://nodejs.org/
- Install Redis for Windows

### Step 2: Clone and Setup Project

```bash
# Clone the repository
git clone https://github.com/your-org/supermon-ng.git
cd supermon-ng

# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install
```

### Step 3: Configure Database

```bash
# Start MySQL
sudo systemctl start mysql  # Linux
brew services start mysql   # macOS

# Create database
mysql -u root -p << 'EOF'
CREATE DATABASE supermon_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'supermon_dev'@'localhost' IDENTIFIED BY 'dev_password';
GRANT ALL PRIVILEGES ON supermon_dev.* TO 'supermon_dev'@'localhost';
FLUSH PRIVILEGES;
EXIT;
EOF

# Initialize database
mysql -u supermon_dev -p supermon_dev < docker/mysql/init.sql
```

### Step 4: Configure Redis

```bash
# Start Redis
sudo systemctl start redis-server  # Linux
brew services start redis          # macOS

# Test Redis connection
redis-cli ping
```

### Step 5: Environment Configuration

```bash
# Copy development environment
cp env.example .env

# Edit configuration
nano .env
```

Update your `.env` file for development:
```env
# Application Settings
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8080

# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=supermon_dev
DB_USERNAME=supermon_dev
DB_PASSWORD=dev_password

# Redis Configuration
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=

# Development Settings
LOG_LEVEL=debug
CACHE_DRIVER=file
SESSION_DRIVER=file
```

### Step 6: Build Frontend Assets

```bash
# Build for development
npm run dev

# Or build for production
npm run build
```

### Step 7: Start Development Server

```bash
# Start PHP development server
php -S localhost:8080

# Or use the built-in development script
./scripts/dev-server.sh
```

## 🔧 Development Tools

### PHP Development Server
```bash
# Simple development server
php -S localhost:8080

# With specific document root
php -S localhost:8080 -t public/

# With custom router
php -S localhost:8080 router.php
```

### Node.js Development Server
```bash
# Start Vite development server
npm run dev

# Build and watch
npm run build:watch
```

### Database Management
```bash
# Access MySQL
mysql -u supermon_dev -p supermon_dev

# Reset database
mysql -u supermon_dev -p supermon_dev < docker/mysql/init.sql

# Create test data
php scripts/create-test-data.php
```

## 🧪 Testing

### Run Tests
```bash
# PHP Unit tests
vendor/bin/phpunit

# JavaScript tests
npm test

# E2E tests
npm run test:e2e

# All tests
npm run test:all
```

### Code Quality
```bash
# PHP linting
vendor/bin/phpstan analyse

# JavaScript linting
npm run lint

# Code formatting
npm run format
```

## 🔍 Debugging

### PHP Debugging
```bash
# Enable error display
php -d display_errors=1 -d display_startup_errors=1 -S localhost:8080

# Check PHP configuration
php -i | grep -E "(memory_limit|max_execution_time|opcache)"

# Debug specific file
php -d xdebug.mode=debug your-file.php
```

### Database Debugging
```bash
# Check MySQL status
sudo systemctl status mysql

# View MySQL logs
sudo tail -f /var/log/mysql/error.log

# Check connections
mysql -u root -p -e "SHOW PROCESSLIST;"
```

### Redis Debugging
```bash
# Check Redis status
sudo systemctl status redis-server

# Monitor Redis
redis-cli monitor

# Check Redis info
redis-cli info
```

## 📊 Development Monitoring

### Enable Development Monitoring
```bash
# Install development monitoring tools
composer require --dev symfony/var-dumper
composer require --dev barryvdh/laravel-debugbar

# Enable debug mode in .env
APP_DEBUG=true
LOG_LEVEL=debug
```

### Performance Profiling
```bash
# Install Xdebug for profiling
sudo apt install php8.2-xdebug

# Configure Xdebug in php.ini
xdebug.mode=profile
xdebug.output_dir=/tmp/xdebug
```

## 🔄 Development Workflow

### Daily Development
```bash
# Start development environment
./scripts/dev-start.sh

# Make changes to code
# Test changes
npm run test

# Commit changes
git add .
git commit -m "Your changes"
git push
```

### Feature Development
```bash
# Create feature branch
git checkout -b feature/your-feature

# Develop feature
# Run tests
npm run test:all

# Create pull request
git push origin feature/your-feature
```

### Hot Reloading
```bash
# Start development with hot reload
npm run dev

# In another terminal, start PHP server
php -S localhost:8080
```

## 🐛 Common Issues

### Permission Issues
```bash
# Fix file permissions
chmod -R 755 .
chmod -R 777 user_files/
chmod -R 777 logs/
```

### Database Connection Issues
```bash
# Check MySQL is running
sudo systemctl status mysql

# Check user permissions
mysql -u root -p -e "SHOW GRANTS FOR 'supermon_dev'@'localhost';"

# Reset database user
mysql -u root -p << 'EOF'
DROP USER IF EXISTS 'supermon_dev'@'localhost';
CREATE USER 'supermon_dev'@'localhost' IDENTIFIED BY 'dev_password';
GRANT ALL PRIVILEGES ON supermon_dev.* TO 'supermon_dev'@'localhost';
FLUSH PRIVILEGES;
EOF
```

### Redis Connection Issues
```bash
# Check Redis is running
sudo systemctl status redis-server

# Test Redis connection
redis-cli ping

# Check Redis configuration
redis-cli config get requirepass
```

### Port Conflicts
```bash
# Check what's using port 8080
sudo lsof -i :8080

# Kill process using port
sudo kill -9 <PID>

# Or use different port
php -S localhost:8081
```

## 📝 Development Scripts

### Create Development Scripts
```bash
# Create dev-start.sh
cat > scripts/dev-start.sh << 'EOF'
#!/bin/bash
echo "Starting Supermon-ng development environment..."

# Start services
sudo systemctl start mysql
sudo systemctl start redis-server

# Install dependencies
composer install
npm install

# Build assets
npm run dev

echo "Development environment ready!"
echo "Access at: http://localhost:8080"
EOF

chmod +x scripts/dev-start.sh
```

### Development Server Script
```bash
# Create dev-server.sh
cat > scripts/dev-server.sh << 'EOF'
#!/bin/bash
echo "Starting PHP development server..."
echo "Access at: http://localhost:8080"
echo "Press Ctrl+C to stop"

php -S localhost:8080 -d display_errors=1 -d display_startup_errors=1
EOF

chmod +x scripts/dev-server.sh
```

## 🎯 IDE Configuration

### VS Code Extensions
- PHP Intelephense
- PHP Debug
- MySQL
- Redis
- GitLens
- Prettier
- ESLint

### PHPStorm Configuration
- Configure PHP interpreter
- Set up database connection
- Configure debugging
- Set up code style

This development setup gives you a fast, flexible environment for developing Supermon-ng features!
