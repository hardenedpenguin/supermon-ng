# Supermon-NG V4.0.6 - Modern AllStar Link Management Dashboard

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
# Download the release to your home directory (avoids /tmp permission issues)
cd $HOME
wget https://github.com/hardenedpenguin/supermon-ng/releases/download/V4.0.6/supermon-ng-V4.0.6.tar.xz

# Extract to your home directory
tar -xJf supermon-ng-V4.0.6.tar.xz

# Run installation script
cd $HOME/supermon-ng
sudo ./install.sh
```

### Installation Options

The installation script supports several options:

```bash
# Normal installation with automatic Apache configuration
sudo ./install.sh

# Installation without Apache configuration (for advanced users)
sudo ./install.sh --skip-apache

# Show help and available options
sudo ./install.sh --help
```

#### `--skip-apache` Option

Use this option if you want to:
- Manage your own web server configuration
- Use Nginx or another web server instead of Apache
- Deploy in a containerized environment
- Have custom Apache configuration requirements

When using `--skip-apache`:
- ✅ Backend service is still installed and started
- ✅ Apache configuration template is still created for reference
- ✅ All other components are installed normally
- ⚠️ You must manually configure your web server
- ⚠️ The backend API will be available on `http://localhost:8000/api`

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

The installation script automatically configures Apache for you! It handles:

- ✅ Enabling required Apache modules (`proxy`, `proxy_http`, `proxy_wstunnel`, `rewrite`, `headers`)
- ✅ Creating the site configuration file (`/etc/apache2/sites-available/supermon-ng.conf`)
- ✅ Enabling the supermon-ng site
- ✅ Testing the Apache configuration
- ✅ Restarting Apache

**No manual configuration required!** The installer does everything automatically.



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
- **AutoSky Integration**: Configure AutoSky paths if using weather alerts
- **Alternative**: If AutoSky isn't available, use `CUSTOM_LINK` to set a clickable weather/alert link instead (e.g., weather.gov, local emergency management, etc.)

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
# Advanced user management
sudo php /var/www/html/supermon-ng/scripts/manage_users.php --help
```

**Important Note:**
The authentication system only manages the `.htaccess` file for user credentials. It does not modify any other system files, Apache configuration, or application settings.

### Role-Based Access Control

After creating user accounts, configure role-based permissions by editing `/var/www/html/supermon-ng/user_files/authusers.inc`:

**Single User Setup:**
If you're the only user, replace "anarchy" with your username throughout the file:

```bash
# Replace all instances of "anarchy" with your username
sudo sed -i 's/"anarchy"/"yourusername"/g' /var/www/html/supermon-ng/user_files/authusers.inc
```

**Multiple User Setup:**
For multiple users, edit the file manually or use sed commands:

```bash
# Add a second user to all permissions
sudo sed -i 's/array("anarchy")/array("anarchy", "newuser")/g' /var/www/html/supermon-ng/user_files/authusers.inc
```

**Permission Categories:**
- **Basic Access**: Connect, Disconnect, Monitor buttons (`$CONNECTUSER`, `$DISCUSER`, `$MONUSER`)
- **Advanced Features**: DTMF, RPT Stats, Favorites (`$DTMFUSER`, `$RSTATUSER`, `$FAVUSER`)
- **Administrative**: Control Panel, Config Editor, System Control (`$CTRLUSER`, `$CFGEDUSER`, `$SYSINFUSER`)

**Security Note:**
Administrative permissions marked with (*) are security-sensitive. Only grant these to trusted users.

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

**Supermon-NG Backend Service:**

The backend service provides the API endpoints for the web interface:

```bash
# Check backend service status
sudo systemctl status supermon-ng-backend

# Start/stop/restart backend service
sudo systemctl start supermon-ng-backend
sudo systemctl stop supermon-ng-backend
sudo systemctl restart supermon-ng-backend

# Enable/disable automatic startup
sudo systemctl enable supermon-ng-backend
sudo systemctl disable supermon-ng-backend

# View backend service logs
sudo journalctl -u supermon-ng-backend -f

# Test backend API directly
curl http://localhost:8000/api/system/info
```

**Other Services:**

```bash
# Check Apache status
sudo systemctl status apache2

# Restart Apache (after configuration changes)
sudo systemctl restart apache2

# Check node status service (if configured)
sudo systemctl status supermon-ng-node-status.service

# View node status logs
sudo journalctl -u supermon-ng-node-status.service -f
```

### Log Files

- **Apache Access**: `/var/log/apache2/supermon-ng_access.log`
- **Apache Error**: `/var/log/apache2/supermon-ng_error.log`
- **Node Status**: `/var/log/supermon-ng-node-status.log`
- **Asterisk**: `/var/log/asterisk/messages`

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

# 1. Missing modules (should be enabled automatically during installation)
sudo a2enmod proxy proxy_http proxy_wstunnel rewrite headers

# 2. Permission issues
sudo chown -R www-data:www-data /var/www/html/supermon-ng/
sudo chmod -R 755 /var/www/html/supermon-ng/
```

**Site not accessible**
```bash
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

Supermon-NG includes an intelligent, conservative update system that **NEVER** replaces critical user files and only advises about user_files changes when the configuration structure actually changes.

### 🚀 Quick Update Process

**For most updates, simply run:**

```bash
# 1. Download the latest release to your home directory
cd $HOME
wget https://github.com/hardenedpenguin/supermon-ng/releases/download/V4.0.6/supermon-ng-V4.0.6.tar.xz

# 2. Extract the new version
tar -xJf supermon-ng-V4.0.6.tar.xz
cd $HOME/supermon-ng

# 3. Run the update script
sudo ./scripts/update.sh
```

**That's it!** The update script handles everything automatically.

### Update Options

The update script also supports the `--skip-apache` option:

```bash
# Normal update with Apache configuration
sudo ./scripts/update.sh

# Update without Apache configuration changes
sudo ./scripts/update.sh --skip-apache

# Show help and available options
sudo ./scripts/update.sh --help
```

Use `--skip-apache` if you have custom web server configuration that you want to preserve.

### 🔍 What the Update Script Does

The `update.sh` script intelligently:

- **Detects Version Changes**: Compares current vs. new version
- **Analyzes Configuration Changes**: Only updates user_files when configs actually change
- **Creates Automatic Backups**: Timestamped backups before any changes
- **Preserves User Configurations**: Keeps your customizations when possible
- **Updates System Services**: Handles systemd, Apache, and dependencies
- **Validates Everything**: Tests configurations and restarts services

### 🔒 Critical File Protection

The update system **NEVER** replaces these critical user files, regardless of template differences:

**User Configuration Files (in `user_files/`):**
- `allmon.ini` - Node configuration (NEVER replace)
- `authusers.inc` - User authentication (NEVER replace)
- `authini.inc` - Authentication settings (NEVER replace)
- `favorites.ini` - User favorites (NEVER replace)
- `privatenodes.txt` - Private nodes list (NEVER replace)
- `controlpanel.ini` - Control panel settings (NEVER replace)

**Root-Level Critical Files:**
- `.htpasswd` - Apache authentication file (NEVER replace)
- `astdb.txt` - Asterisk database (NEVER replace)

**Directories Always Preserved:**
- `sbin/` - User scripts and configurations
- `preferences/` - User preference files

**System Files (NOT backed up):**
- `/etc/sudoers.d/` files
- `/etc/systemd/system/` service files
- `/etc/apache2/sites-available/` configuration files

This ensures your critical node configurations, authentication data, and user customizations are never lost during updates.

### 📋 Detailed Update Instructions

#### Step 1: Check Current Version

Before updating, check your current version:

```bash
# Check current version and system status
sudo /var/www/html/supermon-ng/scripts/version-check.sh
```

This shows:
- Current version and date
- Service status (backend, Apache, node status)
- Configuration file status
- Access URLs
- Update instructions

#### Step 2: Download New Version

```bash
# Create update directory in your home directory
mkdir -p $HOME/supermon-ng-update
cd $HOME/supermon-ng-update

# Download latest release (replace V4.0.5 with actual version)
wget https://github.com/hardenedpenguin/supermon-ng/releases/download/V4.0.6/supermon-ng-V4.0.6.tar.xz

# Extract the package
tar -xJf supermon-ng-V4.0.6.tar.xz
cd supermon-ng
```

#### Step 3: Run Update Script

```bash
# Run the update script
sudo ./scripts/update.sh
```

The script will:

1. **Detect Current Version**: Shows what version you're currently running
2. **Compare Versions**: Determines if update is needed
3. **Analyze Configuration Changes**: Checks if user_files need updating
4. **Create Backups**: Backs up current installation
5. **Update Application**: Installs new files while preserving configurations
6. **Update Services**: Updates systemd services and Apache configuration
7. **Update Dependencies**: Updates PHP and Node.js dependencies
8. **Update Frontend**: Deploys new frontend files
9. **Validate Installation**: Tests configuration and restarts services
10. **Display Summary**: Shows what was updated and next steps

#### Step 4: Review Update Results

After the update completes, you'll see a summary like:

```
🎉 Update Complete!
==================

📊 Update Summary:
   ✅ Updated from V4.0.2 to V4.0.3
   ✅ Application files updated
   ✅ System services updated
   ✅ Dependencies updated
   ✅ Frontend updated
   ✅ User configurations preserved (no changes detected)

🌐 Access your updated Supermon-NG application at:
   - http://localhost
   - http://192.168.1.100
   - http://10.0.0.50

🔧 Service Status:
   ✅ Backend: Running
   ✅ Apache: Running
```

### 🔧 Configuration Change Handling

#### Conservative Update Approach

The update system uses a **conservative approach** that prioritizes data preservation:

**Critical Files (ALWAYS Preserved):**
- Your `allmon.ini`, `authusers.inc`, `authini.inc`, `favorites.ini`, `privatenodes.txt`, `controlpanel.ini`, `.htpasswd`, and `astdb.txt` are **NEVER** replaced
- These files contain your actual node configurations, user accounts, and system data
- They are preserved regardless of whether they match the tarball templates

**Configuration Change Detection:**
- Only checks for new system variables in `common.inc` (core system changes)
- Does NOT compare user-specific files against templates
- Only flags changes when new configuration variables are added to the core system

#### When No Configuration Changes Are Detected

If the update script detects no configuration changes:

```
✅ No significant configuration changes detected.
✅ All user configuration files will be preserved.
```

**What this means:**
- All your critical files are completely preserved
- No manual configuration work needed
- Your customizations remain intact
- Update is complete and ready to use

#### When Configuration Changes Are Detected

If new system variables are found in `common.inc`:

```
⚠️ Configuration changes detected in core system files.
✅ Critical user files (allmon.ini, authusers.inc, etc.) will be preserved.
⚠️ Configuration changes detected in global.inc
📁 Your original global.inc has been backed up to: /tmp/supermon-ng-backup-20250101_120000/user_files
🔄 Running configuration migration for global.inc...
```

**What this means:**
- New system configuration options may be available
- Your critical files (allmon.ini, auth files, etc.) are still preserved
- Only `global.inc` may be updated with new system variables
- The migration system intelligently merges new defaults with your existing values

**Next steps:**
1. Your critical files are already preserved - no action needed
2. Review `global.inc` to see if any new options were added
3. Test the web interface to ensure everything works
4. The migration system handles most changes automatically

### 🛠️ Manual Update Process (Advanced)

If you prefer manual control or need to troubleshoot:

#### Step 1: Create Manual Backup

```bash
# Create backup of user data only (system files are managed by installation)
sudo tar -czf /tmp/supermon-ng-manual-backup-$(date +%Y%m%d_%H%M%S).tar.gz \
    /var/www/html/supermon-ng/user_files/ \
    /var/www/html/supermon-ng/.htpasswd \
    /var/www/html/supermon-ng/astdb.txt
```

**Note:** System files (sudoers, systemd, Apache configs) are not backed up as they are managed by the installation process.

#### Step 2: Stop Services

```bash
# Stop all Supermon-NG services
sudo systemctl stop supermon-ng-backend
sudo systemctl stop supermon-ng-node-status.timer
```

#### Step 3: Update Files

```bash
# Copy new application files (preserve user_files)
sudo cp -r /tmp/supermon-ng-update/* /var/www/html/supermon-ng/
sudo cp -r /tmp/supermon-ng-manual-backup-*/user_files/* /var/www/html/supermon-ng/user_files/
```

#### Step 4: Update Services

```bash
# Update systemd services
sudo cp /tmp/supermon-ng-update/systemd/*.service /etc/systemd/system/
sudo cp /tmp/supermon-ng-update/systemd/*.timer /etc/systemd/system/

# Reload systemd and restart services
sudo systemctl daemon-reload
sudo systemctl enable supermon-ng-backend
sudo systemctl start supermon-ng-backend
```

#### Step 5: Update Dependencies

```bash
# Update PHP dependencies
cd /var/www/html/supermon-ng
sudo -u www-data composer install --no-dev --optimize-autoloader

# Update frontend
cd frontend
npm install
npm run build
cp -r dist/* /var/www/html/supermon-ng/public/
```

### 🔄 Rollback Process

If you need to rollback to a previous version:

#### Using Automatic Backup

```bash
# Stop services
sudo systemctl stop supermon-ng-backend
sudo systemctl stop supermon-ng-node-status.timer

# Restore from backup
sudo tar -xzf /tmp/supermon-ng-backup-YYYYMMDD_HHMMSS.tar.gz -C /

# Restart services
sudo systemctl start supermon-ng-backend
sudo systemctl start supermon-ng-node-status.timer
```

#### Using Manual Backup

```bash
# Stop services
sudo systemctl stop supermon-ng-backend

# Restore application files
sudo tar -xzf /tmp/supermon-ng-manual-backup-YYYYMMDD_HHMMSS.tar.gz -C /

# Restart services
sudo systemctl daemon-reload
sudo systemctl start supermon-ng-backend
```

### 🚨 Troubleshooting Updates

#### Update Script Fails

If the update script fails:

```bash
# Check the update log
sudo tail -f /var/www/html/supermon-ng/logs/migration.log

# Check system status
sudo /var/www/html/supermon-ng/scripts/version-check.sh

# Manual rollback if needed
sudo tar -xzf /tmp/supermon-ng-backup-*.tar.gz -C /
```

#### Services Won't Start

```bash
# Check service status
sudo systemctl status supermon-ng-backend
sudo systemctl status apache2

# Check logs
sudo journalctl -u supermon-ng-backend -f
sudo tail -f /var/log/apache2/supermon-ng_error.log

# Test configuration
sudo apache2ctl configtest
```

#### Configuration Issues

```bash
# Compare with backup (if migration was run)
diff -r /var/www/html/supermon-ng/user_files/ /tmp/supermon-ng-backup-*/user_files/

# Restore specific files (critical files should never need this)
sudo cp /tmp/supermon-ng-backup-*/user_files/global.inc /var/www/html/supermon-ng/user_files/
```

**Note:** With the conservative update system, critical files like `allmon.ini`, `authusers.inc`, `.htpasswd`, and `astdb.txt` are never replaced, so configuration issues are rare.

### 📊 Update Best Practices

1. **Trust the Conservative System**: The update system is designed to preserve your critical data - it will never replace your node configurations, user accounts, or authentication files

2. **Automatic Backups**: The update script creates automatic backups of user data (system files are not backed up as they're managed by installation)

3. **Test in Non-Production**: Test updates on a development system first when possible

4. **Review Release Notes**: Check the release notes for breaking changes or new requirements

5. **Monitor After Update**: Check logs and functionality after updating

6. **Keep Backups**: Don't delete backup files immediately - keep them for a few days

### 🔍 Version Information

Check your current version anytime:

```bash
# Quick version check
sudo /var/www/html/supermon-ng/scripts/version-check.sh

# Detailed system information
sudo systemctl status supermon-ng-backend
sudo systemctl status apache2
```

### 📝 Update Checklist

Before updating:

- [ ] Check current version with `version-check.sh`
- [ ] Review release notes for breaking changes
- [ ] Create manual backup (recommended)
- [ ] Ensure you have root/sudo access
- [ ] Plan for brief service downtime

After updating:

- [ ] Verify services are running
- [ ] Test web interface functionality
- [ ] Check configuration files if changes were detected
- [ ] Monitor logs for any errors
- [ ] Update any new configuration options as needed


## 📋 Quick Reference

### Installation Commands
```bash
# Normal installation
sudo ./install.sh

# Installation without Apache configuration
sudo ./install.sh --skip-apache

# Show installation help
sudo ./install.sh --help
```

### Update Commands
```bash
# Check current version and system status
sudo /var/www/html/supermon-ng/scripts/version-check.sh

# Quick update (download, extract, run update script)
cd $HOME && wget https://github.com/hardenedpenguin/supermon-ng/releases/download/V4.0.6/supermon-ng-V4.0.6.tar.xz
tar -xJf supermon-ng-V4.0.6.tar.xz && cd supermon-ng
sudo ./scripts/update.sh

# Update without Apache configuration changes
sudo ./scripts/update.sh --skip-apache

# Show update help
sudo ./scripts/update.sh --help

# Manual backup before update
sudo tar -czf /tmp/supermon-ng-backup-$(date +%Y%m%d_%H%M%S).tar.gz /var/www/html/supermon-ng/user_files/
```

### Apache Configuration Commands
```bash
# Apache is automatically configured during installation!
# No manual steps required.

# Troubleshooting (if needed):
sudo apache2ctl configtest
sudo systemctl restart apache2
sudo systemctl status apache2
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
- **PHP Version**: Output of `php --version`
- **Apache Error Log Messages**: Recent entries from `/var/log/apache2/supermon-ng_error.log`
- **Steps to Reproduce**: Detailed step-by-step instructions to recreate the issue
- **Screenshots**: If applicable, especially for UI/display issues

**To gather error log information:**
```bash
# View recent Apache error log entries
sudo tail -50 /var/log/apache2/supermon-ng_error.log

# View backend service logs
sudo journalctl -u supermon-ng-backend --since "1 hour ago"
```

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

**Supermon-NG V4.0.6** - Bringing AllStar Link management into the modern era! 🚀📡
