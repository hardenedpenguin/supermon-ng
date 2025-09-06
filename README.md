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
- **Node.js**: 16+ and npm (for development builds only)
- **RAM**: 512MB minimum, 1GB recommended
- **Storage**: 200MB free space
- **AllStar Link**: ASL3+ installation with Asterisk

## 🚀 Installation Methods

### Method 1: Production Release (Recommended)

Download and extract the latest release tarball:

```bash
# Download the release
cd /tmp
wget https://github.com/your-repo/supermon-ng/releases/download/v4.0.0/supermon-ng-V4.0.0.tar.xz

# Extract to web directory
sudo tar -xJf supermon-ng-V4.0.0.tar.xz -C /var/www/html/

# Run installation
cd /var/www/html/supermon-ng
sudo ./install.sh
```

### Method 2: Development Installation

Clone the repository for development or latest features:

```bash
# Clone repository
cd /var/www/html
sudo git clone https://github.com/your-repo/supermon-ng.git
cd supermon-ng

# Run installation (will build frontend from source)
sudo ./install.sh
```

## 🔧 Installation Script Features

The `install.sh` script automatically handles:

- **Dependency Installation**: PHP, Apache modules, ACL tools
- **Frontend Building**: Compiles Vue.js application (if building from source)
- **Apache Configuration**: Creates optimized virtual host with proxy support
- **Security Setup**: Configures sudoers, file permissions, and ACLs
- **Systemd Services**: Sets up node status update service and timer
- **User Management**: Installs password management tools
- **Log Access**: Configures proper permissions for Apache and Asterisk logs

## ⚙️ Configuration

### 1. Node Configuration

Edit `/var/www/html/supermon-ng/user_files/allmon.ini`:

```ini
# Example node configuration
[node_12345]
host=localhost
port=5038
username=admin
password=your_secure_password
context=radio-secure
label=My Repeater
location=City, State

# Set the default node (shown on initial load)
default_node=12345
```

### 2. AMI (Asterisk Manager Interface) Setup

Configure Asterisk Manager Interface in `/etc/asterisk/manager.conf`:

```ini
[general]
enabled = yes
port = 5038
bindaddr = 127.0.0.1

[admin]
secret = your_secure_password
read = system,call,log,verbose,agent,user,config,dtmf,reporting,cdr,dialplan
write = system,call,agent,user,config,command,reporting,originate
```

Restart Asterisk after changes:
```bash
sudo systemctl restart asterisk
```

### 3. Optional: HamClock Integration

To enable HamClock integration, edit `/var/www/html/supermon-ng/user_files/global.inc`:

```php
<?php
// HamClock Configuration
$HAMCLOCK_ENABLED = true;
$HAMCLOCK_URL = "http://your-hamclock-server:8082";
?>
```

### 4. Optional: Custom Header Background

Place custom background images in `/var/www/html/supermon-ng/user_files/`:
- `custom_background.jpg`
- `custom_background.png`
- `custom_background.gif`
- `custom_background.webp`

The system will automatically detect and use custom backgrounds.

### 5. Node Status Updates (Optional)

Configure automatic node status updates by creating `/var/www/html/supermon-ng/user_files/node_info.ini`:

```ini
[nodes]
546051 = "Repeater Site 1"
546055 = "Remote Base"
546056 = "Portable Node"

[weather]
enabled = true
api_key = your_weather_api_key
location = "City, State"

[alerts]
enabled = true
check_interval = 300
```

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

**Error: Frontend build fails**
```bash
# Check Node.js version
node --version  # Should be 16+

# Clear npm cache
cd frontend && npm cache clean --force
npm install
```

### Runtime Issues

**Dashboard shows 404 errors**
```bash
# Check Apache configuration
sudo apache2ctl configtest
sudo systemctl restart apache2

# Verify files are in place
ls -la /var/www/html/supermon-ng/public/
```

**AMI connection failures**
```bash
# Test AMI connection
telnet localhost 5038

# Check Asterisk status
sudo systemctl status asterisk
sudo asterisk -rx "manager show connected"
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

### Updating from Release

1. **Backup current installation**:
   ```bash
   sudo tar -czf /tmp/supermon-ng-backup-$(date +%Y%m%d).tar.gz /var/www/html/supermon-ng/user_files/
   ```

2. **Download and extract new release**:
   ```bash
   cd /tmp
   wget https://github.com/your-repo/supermon-ng/releases/download/v4.0.1/supermon-ng-V4.0.1.tar.xz
   sudo tar -xJf supermon-ng-V4.0.1.tar.xz -C /var/www/html/ --overwrite
   ```

3. **Run installation script**:
   ```bash
   cd /var/www/html/supermon-ng
   sudo ./install.sh
   ```

### Updating from Git

```bash
cd /var/www/html/supermon-ng
sudo git pull origin main
sudo ./install.sh
```

## 🤝 Contributing

### Development Setup

1. **Clone repository**:
   ```bash
   git clone https://github.com/your-repo/supermon-ng.git
   cd supermon-ng
   ```

2. **Install dependencies**:
   ```bash
   composer install
   cd frontend && npm install
   ```

3. **Start development servers**:
   ```bash
   # Backend (PHP development server)
   cd public && php -S localhost:8000

   # Frontend (Vite development server)
   cd frontend && npm run dev
   ```

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