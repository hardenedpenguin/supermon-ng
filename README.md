# supermon-ng

**supermon-ng** is a modernized and extensible version of the original Supermon dashboard for managing and monitoring Asterisk-based systems such as AllStarLink nodes. It offers a streamlined web interface and compatibility with today's system environments.

## Features

- Responsive and mobile-friendly web UI
- Enhanced security and codebase modernization
- Simple installer script for quick deployment
- Easily customizable and extendable
- Compatible with Debian-based systems

## Upgrading from <2.0.3 instructions
If you are updating from anything before 2.0.3 you will need to modify two config files, these are global.inc and node_info.ini

$HAMCLOCK_URL is no longer and should be updated to the following, user_files/global.inc
```bash
// URL for users accessing from your local network (e.g., 192.168.x.x)
$HAMCLOCK_URL_INTERNAL = "http://YOUR_INTERNAL_IP_OR_HOSTNAME/hamclock/live.html";
// URL for users accessing from the internet
$HAMCLOCK_URL_EXTERNAL = "http://YOUR_EXTERNAL_IP_OR_HOSTNAME/hamclock/live.html";
```

ADD custom link for SkyWarn Alerts to user_files/sbin/node_info.ini, add to bottom of autosky stanza
```bash
CUSTOM_LINK = https://alerts.weather.gov/cap/wwaatmget.php?x=TXC039&y=1
```
You will want to update TX039 for your county code, this should match the county code used in SkyWarn Plus

## 🛠️ Installation

Supermon-ng supports multiple deployment options to suit your needs:

### 🐳 Docker Deployment (Recommended for Production)

**Quick Start with Docker:**
```bash
git clone https://github.com/your-org/supermon-ng.git
cd supermon-ng

# Prepare your user_files directory (see below)
docker-compose up -d
```

**How to use and persist user_files:**
- All configuration and persistent data (such as `global.inc`, `allmon.ini`, etc.) should be placed in the `user_files` directory in your project root **before** running Docker.
- The `docker-compose.yml` file mounts this directory into the container, so any changes you make on the host are instantly reflected in the running app.
- To get started, copy the example files:
  ```bash
  cp user_files/global.inc.example user_files/global.inc
  cp user_files/allmon.ini.example user_files/allmon.ini
  # ...copy any other needed example files...
  # Then edit them with your settings
  nano user_files/global.inc
  nano user_files/allmon.ini
  ```
- You can back up, restore, or edit these files at any time without rebuilding the Docker image.

**Manual Docker Setup:**
```bash
# Build and start all services
docker-compose up -d

# Or with monitoring stack
docker-compose --profile production --profile monitoring up -d
```

*See [Deployment Guide](docs/DEPLOYMENT_GUIDE.md) for detailed Docker instructions.*

### 🏗️ Traditional LAMP Stack

**For users who prefer traditional server setup:**
```bash
# Follow the traditional installation guide
# See docs/TRADITIONAL_INSTALL.md for complete instructions
```

**Quick Traditional Setup:**
```bash
# Install dependencies
sudo apt install apache2 php8.2 mysql-server redis-server

# Clone and configure
git clone https://github.com/your-org/supermon-ng.git
cd supermon-ng
composer install
cp env.production .env
# Edit .env with your settings
```

### 💻 Development Setup

**For local development:**
```bash
# Clone repository
git clone https://github.com/your-org/supermon-ng.git
cd supermon-ng

# Install dependencies
composer install
npm install

# Configure environment
cp env.production .env
# Edit .env for development

# Start development server
php -S localhost:8080
```

*See [Development Setup Guide](docs/DEVELOPMENT_SETUP.md) for detailed development instructions.*

### 📋 Installation Comparison

| Feature | Docker | Traditional | Development |
|---------|--------|-------------|-------------|
| **Ease of Setup** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐ |
| **Production Ready** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐ |
| **Customization** | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Resource Usage** | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ |
| **Monitoring** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐ |
| **Scaling** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐ |

**Choose Docker if:** You want production-ready deployment with monitoring
**Choose Traditional if:** You prefer full control over your environment
**Choose Development if:** You're building features locally

## ⚙️ Configuration

## Creating Releases

To create a release tarball with proper versioning:

```bash
./scripts/create-release.sh
```

This will:
- Extract version information from `includes/common.inc`
- Create a compressed `.tar.xz` package
- Generate comprehensive documentation (INSTALL.md, RELEASE_NOTES.md)
- Create checksums (SHA256, SHA512, MD5)
- Validate the release package

> ⚠️ **Note:** This installer is designed for Debian-based systems (e.g., Debian, Ubuntu, or AllStarLink distributions). Run as root or with `sudo`.
> ⚠️ **Note:** authusers.inc can be enabled/disabled during initial install, you will be prompted for a response.

## ⚠️ Web Login Setup (Required)

Supermon-ng uses a password file at `user_files/.htpasswd` for web login authentication.

- **You must run:**
  ```bash
  ./user_files/set_password.sh
  ```
  to create or manage your web login credentials.
- This script will guide you through creating a username and password.
- The web interface will not allow login until this file exists.
- You can re-run the script at any time to add, remove, or change users.

> **Note:** The password file is not created automatically. You must run the script after installation or when setting up a new deployment.

## Post-Installation

After the installer completes and you have configured an initial user, it is recommended to review and customize user permissions.

You can do this by editing the `authusers.inc` file, which is typically located in your web server's directory for supermon-ng (e.g., `/var/www/html/supermon-ng/`).
Choose the option that works best for you, the sed statement is best if you are the sole admin or the lead admin.
```bash
sudo nano /var/www/html/supermon-ng/user_files/authusers.inc
```
```bash
sudo sed -i 's/admin/username/g' /var/www/html/supermon-ng/user_files/authusers.inc
```
If you are using the sed method, please ensure you replace username with the username you have created for your supermon-ng login.

## Documentation

For detailed configuration and deployment guides, see the [docs/](docs/) directory:

- **[DEPLOYMENT_CONFIGURATION.md](docs/DEPLOYMENT_CONFIGURATION.md)** - Reverse proxy setup, HamClock integration, and advanced configuration
- **[DEVELOPER_GUIDE.md](docs/DEVELOPER_GUIDE.md)** - Development and architecture information
- **[CONTRIBUTING.md](docs/CONTRIBUTING.md)** - How to contribute to the project

## Themes

You can find a few themes I have thrown together to speed up customizing your install

```bash
https://w5gle.us/~anarchy/supermon-ng_themes/
```
Once you download the file you must copy it to /var/www/html/supermon-ng/supermon-ng.css, make sure you do not leave it named as it is downloaded!
> ⚠️ **Note:** Themes are being updated to have full support, Everything but seafoamgreen themese have been updated already..
> 

## Contributions

Contributions, issues, and feature requests are welcome! Please fork the repository and submit a pull request.

## License

[MIT](LICENSE)
