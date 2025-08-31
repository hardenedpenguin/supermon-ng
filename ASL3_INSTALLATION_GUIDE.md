# Supermon-ng ASL3+ Installation Guide

This guide provides step-by-step instructions for installing Supermon-ng on an ASL3+ device.

## Prerequisites

- ASL3+ device with root access
- Internet connectivity for package installation
- Basic knowledge of Linux command line

## System Requirements

- PHP 8.1 or higher
- Node.js 18 or higher
- Composer
- Apache/Nginx web server
- MySQL/MariaDB (optional, for advanced features)

## Step 1: System Preparation

### Update System Packages
```bash
sudo apt update && sudo apt upgrade -y
```

### Install Required System Packages
```bash
sudo apt install -y \
    apache2 \
    php8.1 \
    php8.1-cli \
    php8.1-common \
    php8.1-mysql \
    php8.1-zip \
    php8.1-gd \
    php8.1-mbstring \
    php8.1-curl \
    php8.1-xml \
    php8.1-bcmath \
    php8.1-json \
    php8.1-sqlite3 \
    php8.1-opcache \
    php8.1-intl \
    curl \
    git \
    unzip \
    build-essential
```

### Install Node.js 18
```bash
# Add NodeSource repository
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -

# Install Node.js
sudo apt install -y nodejs

# Verify installation
node --version
npm --version
```

### Install Composer
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
```

## Step 2: Web Server Configuration

### Configure Apache
```bash
# Enable required Apache modules
sudo a2enmod rewrite
sudo a2enmod headers
sudo a2enmod ssl

# Create Apache virtual host configuration
sudo nano /etc/apache2/sites-available/supermon-ng.conf
```

Add the following configuration:
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    ServerAdmin webmaster@your-domain.com
    DocumentRoot /var/www/html/supermon-ng/public
    
    <Directory /var/www/html/supermon-ng/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/supermon-ng_error.log
    CustomLog ${APACHE_LOG_DIR}/supermon-ng_access.log combined
</VirtualHost>
```

```bash
# Enable the site
sudo a2ensite supermon-ng.conf

# Disable default site (optional)
sudo a2dissite 000-default.conf

# Test configuration
sudo apache2ctl configtest

# Restart Apache
sudo systemctl restart apache2
```

## Step 3: Application Installation

### Clone the Repository
```bash
cd /var/www/html
sudo git clone https://github.com/your-repo/supermon-ng.git
sudo chown -R www-data:www-data supermon-ng
sudo chmod -R 755 supermon-ng
```

### Set Up Backend
```bash
cd /var/www/html/supermon-ng

# Install PHP dependencies
sudo -u www-data composer install --no-dev --optimize-autoloader

# Set up environment file
sudo -u www-data cp .env.example .env
sudo nano .env
```

Configure the `.env` file with your settings:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=http://your-domain.com

# Database settings (if using database)
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/supermon-ng/database/supermon.db

# AllStar settings
ASTDB_TXT=/var/www/html/supermon-ng/astdb.txt
USERFILES=/var/www/html/supermon-ng/user_files

# Security settings
SESSION_SECURE_COOKIE=false
SESSION_HTTP_ONLY=true
```

### Set Up Frontend
```bash
cd /var/www/html/supermon-ng/frontend

# Fix npm cache permissions (if needed)
sudo mkdir -p /tmp/npm-cache-www-data
sudo chown www-data:www-data /tmp/npm-cache-www-data
sudo -u www-data npm config set cache /tmp/npm-cache-www-data

# Install Node.js dependencies
sudo -u www-data npm install --cache /tmp/npm-cache-www-data

# Build for production
sudo -u www-data npm run build
```

## Step 4: File Permissions and Security

### Set Proper Permissions
```bash
cd /var/www/html/supermon-ng

# Set ownership
sudo chown -R www-data:www-data .

# Set directory permissions
sudo find . -type d -exec chmod 755 {} \;

# Set file permissions
sudo find . -type f -exec chmod 644 {} \;

# Make specific directories writable
sudo chmod -R 775 user_files/
sudo chmod -R 775 cache/
sudo chmod -R 775 logs/
sudo chmod -R 775 custom/

# Make sure astdb.txt is readable
sudo chmod 644 astdb.txt
```

### Create Required Directories
```bash
# Create directories if they don't exist
sudo -u www-data mkdir -p user_files
sudo -u www-data mkdir -p cache
sudo -u www-data mkdir -p logs
sudo -u www-data mkdir -p custom
sudo -u www-data mkdir -p database
```

## Step 5: AllStar Integration

### Configure AllStar Integration
```bash
# Create symbolic link to AllStar database
sudo ln -sf /var/log/asterisk/astdb.txt /var/www/html/supermon-ng/astdb.txt

# Set up cron job to update astdb.txt
sudo crontab -e
```

Add the following cron job:
```cron
# Update astdb.txt every 5 minutes
*/5 * * * * /usr/bin/cp /var/log/asterisk/astdb.txt /var/www/html/supermon-ng/astdb.txt
```

### Configure AllStar Permissions
```bash
# Add www-data user to asterisk group
sudo usermod -a -G asterisk www-data

# Set permissions for AllStar files
sudo chmod 644 /var/log/asterisk/astdb.txt
sudo chown asterisk:asterisk /var/log/asterisk/astdb.txt
```

## Step 6: SSL Configuration (Recommended)

### Install Certbot
```bash
sudo apt install -y certbot python3-certbot-apache
```

### Obtain SSL Certificate
```bash
sudo certbot --apache -d your-domain.com
```

### Update Apache Configuration for SSL
```bash
sudo nano /etc/apache2/sites-available/supermon-ng-le-ssl.conf
```

The SSL configuration will be automatically created by Certbot.

## Step 7: Firewall Configuration

### Configure UFW Firewall
```bash
# Enable UFW
sudo ufw enable

# Allow SSH
sudo ufw allow ssh

# Allow HTTP and HTTPS
sudo ufw allow 80
sudo ufw allow 443

# Allow AllStar ports (if needed)
sudo ufw allow 4569/udp  # IAX2
sudo ufw allow 5060/udp  # SIP
sudo ufw allow 10000:20000/udp  # RTP

# Check status
sudo ufw status
```

## Step 8: System Service Configuration

### Create Systemd Service (Optional)
```bash
sudo nano /etc/systemd/system/supermon-ng.service
```

Add the following content:
```ini
[Unit]
Description=Supermon-ng Web Application
After=network.target apache2.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/html/supermon-ng
ExecStart=/usr/bin/php -S localhost:8000 -t public
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

```bash
# Enable and start the service
sudo systemctl daemon-reload
sudo systemctl enable supermon-ng
sudo systemctl start supermon-ng
```

## Step 9: Testing and Verification

### Test Web Access
```bash
# Test local access
curl -I http://localhost

# Test from external network
curl -I http://your-domain.com
```

### Check Logs
```bash
# Check Apache logs
sudo tail -f /var/log/apache2/supermon-ng_error.log
sudo tail -f /var/log/apache2/supermon-ng_access.log

# Check application logs
sudo tail -f /var/www/html/supermon-ng/logs/app.log
```

### Verify Permissions
```bash
# Check file ownership
ls -la /var/www/html/supermon-ng/

# Check if astdb.txt is accessible
sudo -u www-data cat /var/www/html/supermon-ng/astdb.txt | head -5
```

## Step 10: Initial Configuration

### Access the Web Interface
1. Open your browser and navigate to `http://your-domain.com`
2. You should see the Supermon-ng interface
3. Configure your nodes and settings through the web interface

### Configure AllStar Nodes
1. Go to the Configuration section
2. Add your AllStar nodes
3. Configure node permissions and settings
4. Test node connectivity

## Troubleshooting

### Common Issues

#### Permission Denied Errors
```bash
# Fix ownership issues
sudo chown -R www-data:www-data /var/www/html/supermon-ng
sudo chmod -R 755 /var/www/html/supermon-ng
```

#### Database Connection Issues
```bash
# Check SQLite database
sudo -u www-data sqlite3 /var/www/html/supermon-ng/database/supermon.db ".tables"
```

#### AllStar Integration Issues
```bash
# Check astdb.txt permissions
ls -la /var/log/asterisk/astdb.txt
ls -la /var/www/html/supermon-ng/astdb.txt

# Check if www-data can read the file
sudo -u www-data cat /var/www/html/supermon-ng/astdb.txt | head -1
```

#### Frontend Build Issues
```bash
# Clear npm cache and rebuild
cd /var/www/html/supermon-ng/frontend
sudo -u www-data npm cache clean --force
sudo -u www-data rm -rf node_modules package-lock.json

# Fix npm cache permissions
sudo mkdir -p /tmp/npm-cache-www-data
sudo chown www-data:www-data /tmp/npm-cache-www-data
sudo -u www-data npm config set cache /tmp/npm-cache-www-data

# Reinstall and build
sudo -u www-data npm install --cache /tmp/npm-cache-www-data
sudo -u www-data npm run build
```

### Log Locations
- Apache logs: `/var/log/apache2/`
- Application logs: `/var/www/html/supermon-ng/logs/`
- AllStar logs: `/var/log/asterisk/`

## Security Considerations

### Regular Updates
```bash
# Update system packages
sudo apt update && sudo apt upgrade -y

# Update application
cd /var/www/html/supermon-ng
sudo -u www-data git pull
sudo -u www-data composer install --no-dev --optimize-autoloader
cd frontend
sudo -u www-data npm install
sudo -u www-data npm run build
```

### Backup Strategy
```bash
# Create backup script
sudo nano /usr/local/bin/backup-supermon-ng.sh
```

Add backup script content:
```bash
#!/bin/bash
BACKUP_DIR="/backup/supermon-ng"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR
tar -czf $BACKUP_DIR/supermon-ng_$DATE.tar.gz -C /var/www/html supermon-ng/
cp /var/log/asterisk/astdb.txt $BACKUP_DIR/astdb_$DATE.txt

# Keep only last 7 days of backups
find $BACKUP_DIR -name "supermon-ng_*.tar.gz" -mtime +7 -delete
find $BACKUP_DIR -name "astdb_*.txt" -mtime +7 -delete
```

```bash
# Make backup script executable
sudo chmod +x /usr/local/bin/backup-supermon-ng.sh

# Add to crontab for daily backups
sudo crontab -e
# Add: 0 2 * * * /usr/local/bin/backup-supermon-ng.sh
```

## Support

For issues and support:
- Check the logs for error messages
- Verify all permissions are set correctly
- Ensure AllStar is running and accessible
- Test network connectivity to the web server

## Conclusion

After completing these steps, you should have a fully functional Supermon-ng installation on your ASL3+ device. The system will provide real-time monitoring of your AllStar nodes with a modern, responsive web interface.

Remember to:
- Regularly update the system and application
- Monitor logs for any issues
- Perform regular backups
- Keep security patches up to date
