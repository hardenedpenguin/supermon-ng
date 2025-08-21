# Supermon-ng v3.0.0

**Supermon-ng** is a modernized, secure, and extensible web-based dashboard for managing and monitoring Asterisk-based systems, particularly AllStarLink nodes. Built with security, performance, and user experience in mind, it provides a comprehensive interface for ham radio operators to monitor and control their AllStar networks.

![Supermon-ng Dashboard](https://img.shields.io/badge/Version-3.0.0-blue) ![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4) ![License](https://img.shields.io/badge/License-MIT-green) ![Platform](https://img.shields.io/badge/Platform-Debian%20%7C%20Ubuntu%20%7C%20AllStarLink-orange)

## 🚀 Key Features

### Core Functionality
- **Real-time Node Monitoring** - Live status of AllStar nodes with dynamic updates
- **Voter System Management** - RTCM receiver monitoring with real-time signal strength visualization
- **System Information Dashboard** - Comprehensive hardware and network monitoring
- **Log Management** - Centralized access to Asterisk, Apache, and system logs
- **Configuration Editor** - Web-based INI file management with syntax highlighting

### Security & Modernization
- **Enhanced Security Framework** - CSRF protection, rate limiting, and secure session management
- **Role-based Access Control** - Granular user permissions and authentication
- **Input Validation** - Comprehensive sanitization and validation of all user inputs
- **Secure File Operations** - Whitelist-based file access and command execution

### User Experience
- **Responsive Design** - Mobile-friendly interface that works on all devices
- **Modern UI Components** - Clean, intuitive interface with dropdown menus
- **Real-time Updates** - Server-Sent Events (SSE) for live data streaming
- **Customizable Themes** - Multiple theme options for personalized appearance

### System Integration
- **AllStarLink Compatibility** - Full support for ASL3+ distribution
- **Asterisk Integration** - Direct AMI (Asterisk Manager Interface) communication
- **GPIO Support** - Raspberry Pi GPIO control and monitoring
- **Database Management** - ASTDB, Echolink, and IRLP data handling

## 📋 System Requirements

- **Operating System**: Debian-based systems (Debian, Ubuntu, AllStarLink distribution)
- **PHP**: 7.4 or higher
- **Web Server**: Apache2 or Nginx
- **Asterisk**: AllStarLink ASL3+ or compatible version
- **Memory**: Minimum 512MB RAM (1GB recommended)
- **Storage**: 100MB available space

## 🛠️ Quick Installation

### Automated Installation (Recommended)

```bash
# Update system and install dependencies
sudo apt update && sudo apt install -y rsync acl

# Download and run the installer
wget -q -O supermon-ng-installer.sh "https://raw.githubusercontent.com/hardenedpenguin/supermon-ng/refs/heads/main/supermon-ng-installer.sh"
chmod +x supermon-ng-installer.sh
sudo ./supermon-ng-installer.sh
```

The installer will:
- Download and extract Supermon-ng
- Configure web server settings
- Set up initial user authentication
- Configure system permissions
- Enable required services

### Manual Installation

For advanced users or custom deployments, see [DEPLOYMENT_CONFIGURATION.md](docs/DEPLOYMENT_CONFIGURATION.md) for detailed manual installation instructions.

## 🔧 Configuration

### Initial Setup

After installation, configure your system:

1. **Edit Configuration Files**:
   ```bash
   sudo nano /var/www/html/supermon-ng/user_files/allmon.ini
   sudo nano /var/www/html/supermon-ng/user_files/global.inc
   ```

2. **Set User Permissions**:
   ```bash
   # For single admin setup
   sudo sed -i 's/admin/yourusername/g' /var/www/html/supermon-ng/user_files/authusers.inc
   
   # Or edit manually for multiple users
   sudo nano /var/www/html/supermon-ng/user_files/authusers.inc
   ```

3. **Configure Node Information**:
   ```bash
   sudo nano /var/www/html/supermon-ng/user_files/sbin/node_info.ini
   ```

### Key Configuration Files

- `user_files/allmon.ini` - Node and system definitions
- `user_files/global.inc` - Global settings and URLs
- `user_files/authusers.inc` - User authentication and permissions
- `user_files/sbin/node_info.ini` - Node-specific information

## 🔄 Upgrading from Previous Versions

### From v2.x to v3.0.0

If upgrading from version 2.x, update these configuration files:

**user_files/global.inc** - Replace `$HAMCLOCK_URL` with:
```php
// URL for users accessing from your local network
$HAMCLOCK_URL_INTERNAL = "http://YOUR_INTERNAL_IP_OR_HOSTNAME/hamclock/live.html";
// URL for users accessing from the internet
$HAMCLOCK_URL_EXTERNAL = "http://YOUR_EXTERNAL_IP_OR_HOSTNAME/hamclock/live.html";
```

**user_files/sbin/node_info.ini** - Add custom SkyWarn alerts:
```ini
[autosky]
CUSTOM_LINK = https://alerts.weather.gov/cap/wwaatmget.php?x=TXC039&y=1
```
*Replace `TXC039` with your county code*

### From v1.x or earlier

Follow the v2.x upgrade path first, then apply the v3.0.0 changes above.

## 🎨 Customization

### Themes

Download and apply custom themes from the available options:

```bash
# Download a theme from the available options
wget https://w5gle.us/~anarchy/supermon-ng_themes/your-chosen-theme.css

# Create your custom CSS file
sudo nano /var/www/html/supermon-ng/css/custom.css
```

**Available Themes**: Browse available themes at [https://w5gle.us/~anarchy/supermon-ng_themes/](https://w5gle.us/~anarchy/supermon-ng_themes/)

### Custom CSS

For local customizations, create `css/custom.css` (not included in repository):
```bash
sudo nano /var/www/html/supermon-ng/css/custom.css
```

You can either:
- Create your own custom CSS from scratch
- Download and modify one of the available themes
- Use the theme as a starting point for your customizations

## 📚 Documentation

Comprehensive documentation is available in the [docs/](docs/) directory:

- **[DEPLOYMENT_CONFIGURATION.md](docs/DEPLOYMENT_CONFIGURATION.md)** - Advanced deployment, reverse proxy setup, and HamClock integration
- **[DEVELOPER_GUIDE.md](docs/DEVELOPER_GUIDE.md)** - Architecture overview, development setup, and API documentation
- **[CONTRIBUTING.md](docs/CONTRIBUTING.md)** - Contribution guidelines and development workflow
- **[RELEASE_PROCESS.md](docs/RELEASE_PROCESS.md)** - Release management and versioning
- **[INSTALLER_IMPROVEMENTS.md](docs/INSTALLER_IMPROVEMENTS.md)** - Installer development and maintenance

## 🔧 Development

### Development Tools

```bash
# Code linting
./scripts/lint-code.sh

# Run tests
./scripts/run-tests.sh

# Development setup
./scripts/dev-setup.sh
```

## 🤝 Contributing

We welcome contributions! Please see [CONTRIBUTING.md](docs/CONTRIBUTING.md) for:

- Code style guidelines
- Pull request process
- Issue reporting
- Development setup

### Quick Start for Contributors

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Run tests and linting
5. Submit a pull request

## 🐛 Troubleshooting

### Common Issues

**Permission Errors**: Ensure proper file ownership:
```bash
sudo chown -R www-data:www-data /var/www/html/supermon-ng/
sudo chmod -R 755 /var/www/html/supermon-ng/
```

**Asterisk Connection Issues**: Verify AMI configuration:
```bash
sudo nano /etc/asterisk/manager.conf
```

**Log Access Problems**: Check log file permissions and paths in `includes/common.inc`

### Getting Help

- **Issues**: [GitHub Issues](https://github.com/hardenedpenguin/supermon-ng/issues)
- **Documentation**: [docs/](docs/) directory
- **Community**: AllStarLink forums and ham radio communities

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Original Supermon development team
- AllStarLink community
- Contributors and testers
- Ham radio community support

## 📞 Support

For support, questions, or feature requests:

- **GitHub Issues**: [Create an issue](https://github.com/hardenedpenguin/supermon-ng/issues)
- **Documentation**: Check the [docs/](docs/) directory first
- **Community**: Engage with the AllStarLink community

---

**Supermon-ng v3.0.0** - Modern AllStar Management Dashboard  
*Built with ❤️ for the ham radio community*
