# Supermon-NG V4.0.0 - Modern AllStar Link Management Dashboard

A modern, responsive web-based management interface for AllStar Link nodes, built with Vue.js 3 and PHP 8. This is a complete rewrite of the original Supermon with enhanced features, better security, and modern web technologies.

## ✨ Features

- **Modern Vue.js 3 Frontend** - Responsive, fast, and intuitive interface
- **Real-time Node Monitoring** - Live status updates and statistics
- **HamClock Integration** - Embedded HamClock display with modal support
- **Node Status Management** - Automated Asterisk variable updates
- **User Authentication** - Secure login system with role-based permissions
- **System Information** - CPU, memory, disk usage, and temperature monitoring
- **Configuration Management** - Web-based editing of configuration files
- **Custom Theming** - Support for custom header backgrounds
- **Log Viewing** - Access to system and application logs
- **Control Panel** - Execute AllStar commands remotely

## 📋 System Requirements

- **Operating System**: Debian 11+ or Ubuntu 20.04+ (ASL3+ compatible)
- **PHP**: 8.0+ with extensions: `sqlite3`, `curl`, `mbstring`, `json`
- **Apache**: 2.4+ with modules: `rewrite`, `proxy`, `proxy_http`, `proxy_wstunnel`, `headers`, `expires`
- **RAM**: 512MB minimum, 1GB recommended
- **Storage**: 200MB free space
- **AllStar Link**: ASL3+ installation with Asterisk

## 🚀 Installation

Download and extract the latest release tarball:

```bash
# Download the release
cd /tmp
wget https://github.com/your-repo/supermon-ng/releases/download/v4.0.0/supermon-ng-V4.0.0.tar.xz

# Extract to temporary directory
tar -xJf supermon-ng-V4.0.0.tar.xz

# Run installation script
cd /tmp/supermon-ng
sudo ./install.sh
```

## 🔧 Installation Script Features

The `install.sh` script automatically handles:

- **Dependency Installation**: PHP, Apache modules, ACL tools
- **Frontend Setup**: Deploys pre-built Vue.js application
- **Apache Configuration**: Creates optimized virtual host with proxy support
- **Security Setup**: Configures sudoers, file permissions, and ACLs
- **Systemd Services**: Sets up node status update service and timer
- **User Management**: Installs password management tools
- **Log Access**: Configures proper permissions for Apache and Asterisk logs

## ⚙️ Configuration

### 1. Apache Web Server Configuration

After running `install.sh`, you **must** complete the Apache configuration manually. The installation script creates a template but cannot automatically configure Apache for security reasons.

#### Step-by-Step Apache Setup

**1. Enable Required Apache Modules**
```bash
sudo a2enmod proxy
sudo a2enmod proxy_http
sudo a2enmod proxy_wstunnel
sudo a2enmod rewrite
sudo a2enmod headers
sudo a2enmod expires
```

**2. Copy the Configuration Template**

The installer creates a template at `/var/www/html/supermon-ng/apache-config-template.conf`. Copy it to Apache's sites-available directory:

```bash
sudo cp /var/www/html/supermon-ng/apache-config-template.conf /etc/apache2/sites-available/supermon-ng.conf
```

**3. Enable the Site**
```bash
# Enable the new site
sudo a2ensite supermon-ng

# Test the configuration
sudo apache2ctl configtest

# Restart Apache
sudo systemctl restart apache2
```

#### Apache Configuration Template Explained

The generated configuration includes:

```apache
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/html/supermon-ng/public
    
    # Proxy configurations (must come before Directory blocks)
    ProxyPreserveHost On
    
    # Proxy API requests to backend PHP service
    ProxyPass /api http://localhost:8000/api
    ProxyPassReverse /api http://localhost:8000/api
    
    # HamClock proxy (uncomment and modify if using HamClock)
    # ProxyPass /hamclock/ http://192.168.1.100:8082/
    # ProxyPassReverse /hamclock/ http://192.168.1.100:8082/
    # ProxyPass /live-ws ws://192.168.1.100:8082/live-ws
    # ProxyPassReverse /live-ws ws://192.168.1.100:8082/live-ws
    
    # Serve static files and handle Vue.js routing
    <Directory "/var/www/html/supermon-ng/public">
        AllowOverride All
        Require all granted
        
        # Vue.js SPA routing with API/HamClock exclusions
        RewriteEngine On
        RewriteBase /
        RewriteRule ^index\.html$ - [L]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_URI} !^/api/
        RewriteCond %{REQUEST_URI} !^/hamclock/
        RewriteRule . /index.html [L]
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/supermon-ng_error.log
    CustomLog ${APACHE_LOG_DIR}/supermon-ng_access.log combined
</VirtualHost>
```

#### Customizing for Your Domain

To use a custom domain instead of `localhost`, edit the configuration:

```bash
sudo nano /etc/apache2/sites-available/supermon-ng.conf
```

Change:
```apache
ServerName localhost
```

To:
```apache
ServerName your-domain.com
ServerAlias www.your-domain.com
```

#### SSL/HTTPS Configuration (Optional)

For SSL support, create an additional configuration:

```bash
sudo nano /etc/apache2/sites-available/supermon-ng-ssl.conf
```

```apache
<VirtualHost *:443>
    ServerName your-domain.com
    DocumentRoot /var/www/html/supermon-ng/public
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /path/to/your/certificate.crt
    SSLCertificateKeyFile /path/to/your/private.key
    
    # Same proxy and directory configuration as HTTP version
    # ... (copy from the HTTP configuration)
</VirtualHost>
```

Then enable SSL and the new site:
```bash
sudo a2enmod ssl
sudo a2ensite supermon-ng-ssl
sudo systemctl restart apache2
```

### 2. Node Configuration

Edit `/var/www/html/supermon-ng/user_files/allmon.ini`:

```ini
# Example node configuration
[node_12345]
host=localhost:5038
username=admin
password=your_secure_password
menu=yes
system=Nodes
hiddenNodeURL=no

# Set the default node (shown on initial load)
default_node=12345
```

### 3. Optional: HamClock Integration

To enable HamClock integration, edit `/var/www/html/supermon-ng/user_files/global.inc`:

```php
// ========================================
// HAMCLOCK INTEGRATION
// ========================================

// Enable or disable HamClock integration
// NOTE: HamClock MUST be configured with a reverse proxy to work properly
// Direct access to HamClock without reverse proxy will not work
$HAMCLOCK_ENABLED = "True";

// HamClock URL for local network access (e.g., 192.168.x.x)
$HAMCLOCK_URL_INTERNAL = "http://192.168.1.100/hamclock/live.html";

// HamClock URL for external internet access
$HAMCLOCK_URL_EXTERNAL = "https://your-domain.com/hamclock/live.html";
```

**Important Notes:**
- HamClock requires reverse proxy configuration in Apache (see Apache configuration section)
- Replace `192.168.1.100` with your HamClock server's local IP address  
- Replace `your-domain.com` with your external domain name
- The `/hamclock/live.html` path should remain as shown
- You must also uncomment and configure the HamClock proxy lines in your Apache configuration

### 4. Optional: Custom Header Background

You can customize the header background by placing an image file in `/var/www/html/supermon-ng/user_files/`:

**Filename Requirements:**
- Must be named `header-background` with appropriate extension
- Supported formats: `header-background.jpg`, `header-background.jpeg`, `header-background.png`, `header-background.gif`, `header-background.webp`

**Installation:**
```bash
# Copy your image to the user_files directory
sudo cp /path/to/your/image.jpg /var/www/html/supermon-ng/user_files/header-background.jpg

# Set proper permissions
sudo chown www-data:www-data /var/www/html/supermon-ng/user_files/header-background.jpg
sudo chmod 644 /var/www/html/supermon-ng/user_files/header-background.jpg
```

**Notes:**
- Recommended size: 900x164 pixels (matches current header dimensions)
- System automatically detects the first `header-background.*` file found
- Images are cached for better performance

### 5. Node Status Updates (Optional)

Configure automatic node status updates by creating `/var/www/html/supermon-ng/user_files/sbin/node_info.ini`:

```ini
[general]
NODE = 546051 546055 546056
WX_CODE = 77511
WX_LOCATION = Alvin, Texas
TEMP_UNIT = F

[autosky]
MASTER_ENABLE = yes
ALERT_INI = /usr/local/bin/AUTOSKY/AutoSky.ini
WARNINGS_FILE = /var/www/html/AUTOSKY/warnings.txt
CUSTOM_LINK = https://alerts.weather.gov/cap/wwaatmget.php?x=TXC039&y=1
```

**Configuration Options:**
- Replace node numbers in `NODE =` with your actual node numbers (space-separated)
- Set `WX_CODE` to your local weather station code
- Update `WX_LOCATION` with your location
- Set `TEMP_UNIT` to `F` (Fahrenheit) or `C` (Celsius)
- Configure AutoSky paths if using weather alerts

**Dashboard Configuration:**
Users with `SYSINFUSER` permissions can configure node status settings directly through the web dashboard using the "Node Status" button.

This enables the systemd service that updates Asterisk variables every 3 minutes.

## 🌐 Access and Usage

### Web Interface

Access your dashboard at:
- **HTTP**: `http://your-server-ip`
- **HTTPS**: `https://your-server-ip` (if SSL configured)

### Default Features Available

- **Node Selection**: Choose nodes from dropdown menu
- **Real-time Stats**: Live connection counts and node status
- **Control Panel**: Execute AllStar commands (authenticated users)
- **System Information**: View system stats (authenticated users)
- **Display Configuration**: Customize interface appearance

### Authentication

Create user accounts using the included tools:

```bash
# Interactive password setting
sudo /var/www/html/supermon-ng/user_files/set_password.sh

# Advanced user management
sudo php /var/www/html/supermon-ng/scripts/manage_users.php --help
```

## 🔒 Security Features

### File Permissions
- Application runs as `www-data` user
- Sensitive files protected with appropriate permissions
- ACLs configured for log file access

### Sudoers Configuration
- Limited sudo access for `www-data` user
- Only specific commands allowed without password
- Secure script execution for system information

### User Authentication
- Session-based authentication system
- Role-based permissions (SYSINFUSER, CTRLUSER, etc.)
- Secure password hashing

## 🛠️ Management and Maintenance

### Service Management

```bash
# Check Apache status
sudo systemctl status apache2

# Restart Apache (after configuration changes)
sudo systemctl restart apache2

# Check node status service
sudo systemctl status supermon-ng-node-status.service

# View service logs
sudo journalctl -u supermon-ng-node-status.service -f
```

### Log Files

- **Apache Access**: `/var/log/apache2/supermon-ng_access.log`
- **Apache Error**: `/var/log/apache2/supermon-ng_error.log`
- **Node Status**: `/var/log/supermon-ng-node-status.log`
- **Asterisk**: `/var/log/asterisk/messages`

### Configuration File Editor

Use the secure configuration editor:

```bash
# Edit configuration files safely
sudo /usr/local/sbin/supermon_unified_file_editor.sh
```

## 🐛 Troubleshooting

### Installation Issues

**Error: Package dependencies not met**
```bash
sudo apt update && sudo apt upgrade
sudo ./install.sh
```


### Apache Configuration Issues

**Apache configuration test fails**
```bash
# Check configuration syntax
sudo apache2ctl configtest

# Common issues and fixes:

# 1. Missing modules
sudo a2enmod proxy proxy_http proxy_wstunnel rewrite headers expires

# 2. Invalid DocumentRoot path
# Edit /etc/apache2/sites-available/supermon-ng.conf
# Ensure DocumentRoot points to: /var/www/html/supermon-ng/public

# 3. Permission issues
sudo chown -R www-data:www-data /var/www/html/supermon-ng/
sudo chmod -R 755 /var/www/html/supermon-ng/
```

**Site not accessible after Apache configuration**
```bash
# Check if site is enabled
sudo a2ensite supermon-ng

# Check Apache status
sudo systemctl status apache2

# View Apache error logs
sudo tail -f /var/log/apache2/supermon-ng_error.log

# Check if backend service is running
sudo systemctl status supermon-ng-backend

# Test backend directly
curl http://localhost:8000/api/system/info
```

**Proxy errors (502 Bad Gateway)**
```bash
# Backend service not running
sudo systemctl start supermon-ng-backend
sudo systemctl enable supermon-ng-backend

# Check backend logs
sudo journalctl -u supermon-ng-backend -f

# Verify proxy configuration in Apache
grep -A5 -B5 "ProxyPass" /etc/apache2/sites-available/supermon-ng.conf
```

### Runtime Issues

**Dashboard shows 404 errors**
```bash
# Check Apache configuration
sudo apache2ctl configtest
sudo systemctl restart apache2

# Verify files are in place
ls -la /var/www/html/supermon-ng/public/

# Check Apache site configuration
sudo a2ensite supermon-ng
sudo systemctl reload apache2
```

**AMI connection failures**
```bash
# Check Asterisk status
sudo systemctl status asterisk
sudo asterisk -rx "manager show connected"

# Note: AMI configuration is covered in the AllStar documentation
```

**Node Status button not visible**
- Ensure you're logged in with proper permissions
- Check that `SYSINFUSER` permission is granted to your user
- Verify `/var/www/html/supermon-ng/user_files/node_info.ini` exists

### Permission Issues

```bash
# Fix file permissions
sudo chown -R www-data:www-data /var/www/html/supermon-ng
sudo chmod -R 755 /var/www/html/supermon-ng

# Fix log permissions
sudo setfacl -R -m u:www-data:r /var/log/asterisk/
sudo setfacl -R -m u:www-data:r /var/log/apache2/
```

## 📊 Performance Optimization

### Apache Tuning

For high-traffic installations, consider these Apache optimizations in your virtual host:

```apache
# Enable compression
LoadModule deflate_module modules/mod_deflate.so
<Location />
    SetOutputFilter DEFLATE
    SetEnvIfNoCase Request_URI \
        \.(?:gif|jpe?g|png)$ no-gzip dont-vary
    SetEnvIfNoCase Request_URI \
        \.(?:exe|t?gz|zip|bz2|sit|rar)$ no-gzip dont-vary
</Location>

# Enable caching
ExpiresActive On
ExpiresByType text/css "access plus 1 month"
ExpiresByType application/javascript "access plus 1 month"
ExpiresByType image/png "access plus 1 month"
ExpiresByType image/jpg "access plus 1 month"
ExpiresByType image/jpeg "access plus 1 month"
ExpiresByType image/gif "access plus 1 month"
```

### System Resources

Monitor system resources:
```bash
# Check memory usage
free -h

# Check disk space
df -h

# Monitor CPU usage
htop
```

## 🔄 Updates and Upgrades

1. **Backup current installation**:
   ```bash
   sudo tar -czf /tmp/supermon-ng-backup-$(date +%Y%m%d).tar.gz /var/www/html/supermon-ng/user_files/
   ```

2. **Download and extract new release**:
   ```bash
   cd /tmp
   wget https://github.com/your-repo/supermon-ng/releases/download/v4.0.1/supermon-ng-V4.0.1.tar.xz
   tar -xJf supermon-ng-V4.0.1.tar.xz
   ```

3. **Run installation script**:
   ```bash
   cd /tmp/supermon-ng
   sudo ./install.sh
   ```


## 📋 Quick Reference

### Apache Configuration Commands
```bash
# Complete Apache setup after installation
sudo a2enmod proxy proxy_http proxy_wstunnel rewrite headers expires
sudo cp /var/www/html/supermon-ng/apache-config-template.conf /etc/apache2/sites-available/supermon-ng.conf
sudo a2ensite supermon-ng
sudo apache2ctl configtest
sudo systemctl restart apache2
```

### Service Management Commands
```bash
# Backend service
sudo systemctl status supermon-ng-backend
sudo systemctl start supermon-ng-backend
sudo systemctl stop supermon-ng-backend
sudo systemctl restart supermon-ng-backend

# Node status service (if configured)
sudo systemctl status supermon-ng-node-status.service
sudo systemctl status supermon-ng-node-status.timer

# Apache
sudo systemctl status apache2
sudo systemctl restart apache2
sudo apache2ctl configtest
```

### Log File Locations
```bash
# Apache logs
tail -f /var/log/apache2/supermon-ng_error.log
tail -f /var/log/apache2/supermon-ng_access.log

# Backend service logs
sudo journalctl -u supermon-ng-backend -f

# Node status logs
tail -f /var/log/supermon-ng-node-status.log
sudo journalctl -u supermon-ng-node-status.service -f
```

### Configuration File Locations
```bash
# Node configuration
/var/www/html/supermon-ng/user_files/allmon.ini

# Apache configuration
/etc/apache2/sites-available/supermon-ng.conf

# Node status configuration
/var/www/html/supermon-ng/user_files/sbin/node_info.ini

# Global settings
/var/www/html/supermon-ng/user_files/global.inc
```

## 🤝 Contributing

### Code Standards

- **PHP**: Follow PSR-12 coding standards
- **JavaScript/Vue**: Use ESLint configuration provided
- **Git**: Use conventional commit messages

### Pull Request Process

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Make your changes and test thoroughly
4. Commit changes: `git commit -m 'feat: add amazing feature'`
5. Push to branch: `git push origin feature/amazing-feature`
6. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support and Community

### Getting Help

- **GitHub Issues**: Report bugs and request features
- **Documentation**: Check this README and inline code comments
- **Community**: Join AllStar Link forums and groups

### Reporting Issues

When reporting issues, please include:
- Operating system and version
- PHP and Apache versions
- Complete error messages from logs
- Steps to reproduce the problem
- Screenshots if applicable

### Feature Requests

We welcome feature requests! Please:
- Check existing issues first
- Provide detailed use cases
- Consider contributing the feature yourself

## 🙏 Acknowledgments

- **AllStar Link Community** - For the amazing ASL platform
- **Original Supermon** - For the foundation and inspiration
- **Vue.js Team** - For the excellent frontend framework
- **PHP Community** - For the robust backend language

---

**Supermon-NG V4.0.0** - Bringing AllStar Link management into the modern era! 🚀📡