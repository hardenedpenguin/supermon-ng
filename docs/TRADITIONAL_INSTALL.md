# Traditional LAMP Stack Installation

This guide shows how to install Supermon-ng using the traditional LAMP (Linux, Apache, MySQL, PHP) stack without Docker.

## 🚀 Quick Installation

### Prerequisites
- Ubuntu 20.04+ or CentOS 8+
- Root or sudo access
- 2GB RAM minimum
- 20GB disk space

### Step 1: Install System Dependencies

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Apache, PHP, and extensions
sudo apt install -y apache2 php8.2 php8.2-mysql php8.2-redis php8.2-gd php8.2-zip php8.2-mbstring php8.2-xml php8.2-curl

# Install MySQL
sudo apt install -y mysql-server

# Install Redis
sudo apt install -y redis-server

# Install additional tools
sudo apt install -y git curl unzip
```

### Step 2: Configure Apache

```bash
# Enable required modules
sudo a2enmod rewrite
sudo a2enmod headers
sudo a2enmod expires

# Create Apache virtual host
sudo tee /etc/apache2/sites-available/supermon-ng.conf << 'EOF'
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/supermon-ng

    # Security Headers
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    # Directory Configuration
    <Directory /var/www/supermon-ng>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted

        # Security
        <Files "*.ini">
            Require all denied
        </Files>
        <Files "*.log">
            Require all denied
        </Files>
    </Directory>

    # Static File Caching
    <LocationMatch "\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$">
        ExpiresActive On
        ExpiresDefault "access plus 1 year"
        Header set Cache-Control "public, immutable"
    </LocationMatch>

    # Logging
    ErrorLog ${APACHE_LOG_DIR}/supermon-ng_error.log
    CustomLog ${APACHE_LOG_DIR}/supermon-ng_access.log combined
</VirtualHost>
EOF

# Enable the site
sudo a2ensite supermon-ng.conf
sudo a2dissite 000-default.conf

# Restart Apache
sudo systemctl restart apache2
```

### Step 3: Configure PHP

```bash
# Create custom PHP configuration
sudo tee /etc/php/8.2/apache2/conf.d/99-supermon-ng.ini << 'EOF'
; Supermon-ng PHP Configuration
memory_limit = 256M
max_execution_time = 30
max_input_time = 60
post_max_size = 8M
upload_max_filesize = 2M
max_file_uploads = 20

; Error Handling
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php_errors.log
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT

; Session Security
session.cookie_httponly = 1
session.cookie_secure = 0
session.use_strict_mode = 1
session.cookie_samesite = "Strict"
session.gc_maxlifetime = 3600

; Security
expose_php = Off
allow_url_fopen = Off
allow_url_include = Off

; Performance
opcache.enable = 1
opcache.enable_cli = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 4000
opcache.revalidate_freq = 2
opcache.fast_shutdown = 1
opcache.enable_file_override = 1
opcache.validate_timestamps = 0

; Date
date.timezone = UTC
EOF

# Restart Apache to apply PHP changes
sudo systemctl restart apache2
```

### Step 4: Configure MySQL

```bash
# Secure MySQL installation
sudo mysql_secure_installation

# Create database and user
sudo mysql -u root -p << 'EOF'
CREATE DATABASE supermon CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'supermon_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON supermon.* TO 'supermon_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
EOF
```

### Step 5: Configure Redis

```bash
# Edit Redis configuration
sudo tee -a /etc/redis/redis.conf << 'EOF'

# Supermon-ng Redis Configuration
requirepass your_redis_password
maxmemory 256mb
maxmemory-policy allkeys-lru
appendonly yes
EOF

# Restart Redis
sudo systemctl restart redis-server
```

### Step 6: Deploy Application

```bash
# Create application directory
sudo mkdir -p /var/www/supermon-ng
sudo chown $USER:$USER /var/www/supermon-ng

# Clone or copy application files
cd /var/www/supermon-ng
# Copy your Supermon-ng files here

# Set proper permissions
sudo chown -R www-data:www-data /var/www/supermon-ng
sudo chmod -R 755 /var/www/supermon-ng
sudo chmod -R 777 /var/www/supermon-ng/user_files

# Create log directories
sudo mkdir -p /var/log/supermon-ng
sudo chown www-data:www-data /var/log/supermon-ng
```

### Step 7: Configure Environment

```bash
# Copy environment template
cp env.production .env

# Edit configuration
nano .env
```

Update the following in your `.env` file:
```env
# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=supermon
DB_USERNAME=supermon_user
DB_PASSWORD=your_secure_password

# Redis Configuration
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=your_redis_password

# Application Settings
APP_ENV=production
APP_DEBUG=false
APP_URL=http://your-domain.com
```

### Step 8: Install Dependencies

```bash
# Install Composer (if not already installed)
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Node.js dependencies (if using frontend build)
npm ci
npm run build
```

### Step 9: Initialize Database

```bash
# Run database initialization
mysql -u supermon_user -p supermon < docker/mysql/init.sql
```

### Step 10: Configure SSL (Optional but Recommended)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache

# Generate SSL certificate
sudo certbot --apache -d your-domain.com

# Auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

### Step 11: Set Up Monitoring (Optional)

```bash
# Install monitoring tools
sudo apt install -y prometheus grafana

# Configure Prometheus
sudo systemctl enable prometheus
sudo systemctl start prometheus

# Configure Grafana
sudo systemctl enable grafana-server
sudo systemctl start grafana-server
```

## 🔧 Configuration Files

### Apache Configuration Location
- Main config: `/etc/apache2/sites-available/supermon-ng.conf`
- PHP config: `/etc/php/8.2/apache2/conf.d/99-supermon-ng.ini`

### Application Files
- Application root: `/var/www/supermon-ng/`
- Logs: `/var/log/supermon-ng/`
- User files: `/var/www/supermon-ng/user_files/`

### Database
- MySQL config: `/etc/mysql/mysql.conf.d/mysqld.cnf`
- Database: `supermon`

### Redis
- Config: `/etc/redis/redis.conf`
- Data: `/var/lib/redis/`

## 🚀 Starting Services

```bash
# Start all services
sudo systemctl start apache2
sudo systemctl start mysql
sudo systemctl start redis-server

# Enable auto-start
sudo systemctl enable apache2
sudo systemctl enable mysql
sudo systemctl enable redis-server

# Check status
sudo systemctl status apache2 mysql redis-server
```

## 🔍 Troubleshooting

### Check Apache Logs
```bash
sudo tail -f /var/log/apache2/supermon-ng_error.log
sudo tail -f /var/log/apache2/supermon-ng_access.log
```

### Check PHP Logs
```bash
sudo tail -f /var/log/php_errors.log
```

### Check MySQL Status
```bash
sudo systemctl status mysql
sudo mysql -u root -p -e "SHOW PROCESSLIST;"
```

### Check Redis Status
```bash
sudo systemctl status redis-server
redis-cli ping
```

## 📊 Performance Optimization

### Apache Optimization
```bash
# Edit Apache MPM configuration
sudo nano /etc/apache2/mods-available/mpm_prefork.conf
```

### MySQL Optimization
```bash
# Edit MySQL configuration
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```

### PHP OPcache
The OPcache is already configured in the PHP settings above.

## 🔒 Security Hardening

### Firewall Configuration
```bash
sudo ufw allow ssh
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

### Fail2ban Installation
```bash
sudo apt install fail2ban
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

## 📝 Maintenance

### Regular Updates
```bash
# Update system packages
sudo apt update && sudo apt upgrade

# Update application
cd /var/www/supermon-ng
git pull origin main
composer install --no-dev --optimize-autoloader
```

### Backup Procedures
```bash
# Database backup
mysqldump -u supermon_user -p supermon > backup_$(date +%Y%m%d).sql

# Application backup
tar -czf supermon-ng_$(date +%Y%m%d).tar.gz /var/www/supermon-ng/
```

This traditional installation gives you full control over your environment while still providing all the modern features of Supermon-ng!
