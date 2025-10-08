#!/bin/bash

# Supermon-ng Release Script
# Creates a release tarball with proper versioning and documentation

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
    exit 1
}

# Function to extract version from common.inc
extract_version() {
    local version_file="includes/common.inc"
    
    if [[ ! -f "$version_file" ]]; then
        error "Version file $version_file not found"
    fi
    
    # Extract version from TITLE_LOGGED line
    local version=$(grep -o 'V[0-9]\+\.[0-9]\+\.[0-9]\+' "$version_file" | head -1)
    
    if [[ -z "$version" ]]; then
        error "Could not extract version from $version_file"
    fi
    
    echo "$version"
}

# Function to extract version date
extract_version_date() {
    local version_file="includes/common.inc"
    
    if [[ ! -f "$version_file" ]]; then
        error "Version file $version_file not found"
    fi
    
    # Extract date from VERSION_DATE line
    local date=$(grep 'VERSION_DATE' "$version_file" | sed "s/.*VERSION_DATE = \"\(.*\)\";.*/\1/")
    
    if [[ -z "$date" ]]; then
        error "Could not extract version date from $version_file"
    fi
    
    echo "$date"
}

# Function to create release notes
create_release_notes() {
    local version="$1"
    local date="$2"
    local release_dir="$3"
    
    cat > "$release_dir/RELEASE_NOTES.md" << EOF
# Supermon-ng $version Release Notes

**Release Date:** $date

## Overview

Supermon-ng $version is a modernized and extensible version of the original Supermon dashboard for managing and monitoring Asterisk-based systems such as AllStarLink nodes.

## Features

- Responsive and mobile-friendly web UI
- Enhanced security and codebase modernization
- Simple installer script for quick deployment
- Intelligent update system with configuration preservation
- Automatic Apache configuration with IP detection
- Advanced user options (--skip-apache flag)
- Easily customizable and extendable
- Compatible with Debian-based systems
- PWA (Progressive Web App) support
- Modern JavaScript framework integration
- Comprehensive testing suite

## Installation

### Quick Install

\`\`\`bash
# Download and run the installer script
wget -q -O supermon-ng-installer.sh "https://raw.githubusercontent.com/hardenedpenguin/supermon-ng/refs/heads/main/supermon-ng-installer.sh"
chmod +x supermon-ng-installer.sh
sudo ./supermon-ng-installer.sh
\`\`\`

### Manual Installation

1. Extract the tarball to your web server directory
2. Set proper permissions: \`chmod -R 755 /path/to/supermon-ng\`
3. Configure your web server (Apache/Nginx)

## Upgrades

### Automatic Upgrade

Supermon-ng includes an intelligent update system that preserves your configurations:

\`\`\`bash
# Download new version and extract
cd /tmp
wget https://github.com/hardenedpenguin/supermon-ng/releases/download/V4.0.7/supermon-ng-V4.0.7.tar.xz
tar -xJf supermon-ng-V4.0.7.tar.xz
cd supermon-ng

# Run the update script
sudo ./scripts/update.sh
\`\`\`

### Advanced Options

\`\`\`bash
# Skip Apache configuration updates
sudo ./scripts/update.sh --skip-apache

# Check current version and system status
sudo ./scripts/version-check.sh
\`\`\`

The update system intelligently detects configuration changes and only advises about user_files updates when the configuration structure actually changes.

## Configuration

### Essential Files

- \`user_files/global.inc\` - Global configuration and appearance
- \`user_files/authusers.inc\` - User authentication settings
- \`user_files/authini.inc\` - Authentication configuration
- \`user_files/favini.inc\` - Favorites configuration

### Upgrading from <2.0.3

If you are updating from anything before 2.0.3, you will need to modify two config files:

**user_files/global.inc:**
\`\`\`php
// URL for users accessing from your local network
\$HAMCLOCK_URL_INTERNAL = "http://YOUR_INTERNAL_IP_OR_HOSTNAME/hamclock/live.html";
// URL for users accessing from the internet
\$HAMCLOCK_URL_EXTERNAL = "http://YOUR_EXTERNAL_IP_OR_HOSTNAME/hamclock/live.html";
\`\`\`

**user_files/sbin/node_info.ini:**
\`\`\`ini
CUSTOM_LINK = https://alerts.weather.gov/cap/wwaatmget.php?x=TXC039&y=1
\`\`\`

## File Structure

\`\`\`
supermon-ng/
├── includes/          # Core PHP includes and functions
├── user_files/        # User configuration files
├── css/              # Stylesheets
├── js/               # JavaScript files
├── scripts/          # Utility scripts
├── tests/            # Test suite
├── templates/        # Template files
└── *.php            # Main application files
\`\`\`

## Security

- CSRF protection enabled by default
- Input validation and sanitization
- Secure session management
- XSS protection headers
- Content Security Policy (CSP)

## Support

- **Issues:** GitHub Issues
- **Contributions:** Pull requests welcome

## License

MIT License - see LICENSE file for details

## Changelog

### $version ($date)
- Initial release of Supermon-ng
- Modernized codebase and security improvements
- Enhanced user interface and responsiveness
- Comprehensive testing suite
- PWA support and offline capabilities
- Performance optimizations including:
  - PHP OPcache configuration
  - HTTP caching headers and ETag support
  - API response caching system
  - AMI connection pooling and command batching
  - File I/O caching for INI files and ASTDB
  - Optimized logging for production environments
EOF
}

# Function to create installation guide
create_install_guide() {
    local release_dir="$1"
    
    cat > "$release_dir/INSTALL.md" << EOF
# Supermon-ng Installation Guide

## Prerequisites

- Debian-based system (Debian, Ubuntu, AllStarLink)
- Apache web server with PHP support
- PHP 7.4 or higher
- rsync and acl packages

## Quick Installation

### Automated Install (Recommended)

\`\`\`bash
# Download the installer
wget -q -O supermon-ng-installer.sh "https://raw.githubusercontent.com/hardenedpenguin/supermon-ng/refs/heads/main/supermon-ng-installer.sh"

# Make executable and run
chmod +x supermon-ng-installer.sh
sudo ./supermon-ng-installer.sh
\`\`\`

### Manual Installation

1. **Extract Files**
   \`\`\`bash
   sudo tar -xzf supermon-ng-*.tar.xz -C /var/www/html/
   sudo chown -R www-data:www-data /var/www/html/supermon-ng
   \`\`\`

2. **Configure Web Server**
   \`\`\`bash
   # Apache configuration
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   \`\`\`

3. **Set Up Authentication**
   \`\`\`bash
   sudo nano /var/www/html/supermon-ng/user_files/authusers.inc
   # Add your username and password
   \`\`\`

4. **Customize Configuration**
   \`\`\`bash
   sudo nano /var/www/html/supermon-ng/user_files/global.inc
   # Set your callsign, name, location, etc.
   \`\`\`

5. **Configure Performance Optimizations (Optional)**
   \`\`\`bash
   # Include Apache performance configuration
   sudo nano /etc/apache2/sites-available/000-default.conf
   # Add: Include /var/www/html/supermon-ng/config/apache-performance.conf
   
   # Include PHP OPcache optimization
   sudo nano /etc/php/8.4/apache2/conf.d/99-supermon-ng.ini
   # Add: include=/var/www/html/supermon-ng/config/php-opcache.ini
   \`\`\`

## Configuration Files

### Essential Configuration

- \`user_files/global.inc\` - Global settings and appearance
- \`user_files/authusers.inc\` - User accounts and passwords
- \`user_files/authini.inc\` - Authentication settings
- \`user_files/favini.inc\` - Favorites configuration

### Performance Optimization Configuration

- \`config/php-opcache.ini\` - PHP OPcache optimization settings
- \`config/apache-performance.conf\` - Apache performance and caching configuration

### Example global.inc

\`\`\`php
<?php
// Your callsign
\$CALL = "YOUR-CALL";

// Your name
\$NAME = "Your Name";

// Your location
\$LOCATION = "Your Location";

// System title
\$TITLE2 = "AllStar Network Monitor";

// Welcome message
\$WELCOME_MSG = "Welcome to Supermon-ng";
?>
\`\`\`

## Post-Installation

1. **Access the Web Interface**
   - Open your browser to \`http://your-server/supermon-ng/\`
   - Login with the credentials you configured

2. **Customize Appearance**
   - Edit \`user_files/global.inc\` for basic customization
   - Modify CSS files in \`css/\` directory for advanced styling

3. **Set Up Monitoring**
   - Configure AllStar node monitoring
   - Set up log file paths
   - Configure external services

## Troubleshooting

### Common Issues

1. **Permission Denied**
   \`\`\`bash
   sudo chown -R www-data:www-data /var/www/html/supermon-ng
   sudo chmod -R 755 /var/www/html/supermon-ng
   \`\`\`

2. **Page Not Found**
   - Ensure Apache mod_rewrite is enabled
   - Check .htaccess file exists and is readable

3. **Login Issues**
   - Verify authusers.inc is properly configured
   - Check file permissions on user_files directory

### Log Files

- Apache error log: \`/var/log/apache2/error.log\`
- Supermon-ng logs: \`/tmp/SMLOG.txt\`

## Support

For additional help:
- Review the README.md file
- Open an issue on GitHub
EOF
}

# Function to create checksums
create_checksums() {
    local release_file="$1"
    local release_dir="$2"
    
    log "Creating checksums..."
    
    # SHA256
    sha256sum "$release_file" > "$release_dir/$(basename "$release_file").sha256"
    
    # SHA512
    sha512sum "$release_file" > "$release_dir/$(basename "$release_file").sha512"
    
    # MD5 (for legacy compatibility)
    md5sum "$release_file" > "$release_dir/$(basename "$release_file").md5"
    
    success "Checksums created"
}

# Function to validate release
validate_release() {
    local release_dir="$1"
    
    log "Validating release package..."
    
    # Check for essential files
    local required_files=(
        "install.sh"
        ".htaccess"
        "composer.json"
        "includes/common.inc"
        "INSTALL.md"
        "RELEASE_NOTES.md"
    )
    
    for file in "${required_files[@]}"; do
        if [[ ! -f "$release_dir/$file" ]]; then
            warning "Required file missing: $file (will be created)"
        fi
    done
    
    # Check for essential directories
    local required_dirs=(
        "includes"
        "user_files"
        "src"
        "public"
        "frontend"
        "scripts"
    )
    
    for dir in "${required_dirs[@]}"; do
        if [[ ! -d "$release_dir/$dir" ]]; then
            error "Required directory missing: $dir"
        fi
    done
    
    success "Release validation passed"
}

# Main function
main() {
    log "Starting Supermon-ng release creation..."
    
    # Extract version information
    local version=$(extract_version)
    local version_date=$(extract_version_date)
    
    log "Version: $version"
    log "Release Date: $version_date"
    
    # Create release directory (installer expects 'supermon-ng' directory)
    local release_name="supermon-ng-${version}"
    local release_dir="/tmp/supermon-ng"
    local release_file="/tmp/$release_name.tar.xz"
    
    log "Creating release directory: $release_dir"
    rm -rf "$release_dir"
    mkdir -p "$release_dir"
    
    # Copy essential files and directories
    log "Copying production files..."
    
    # Core application directories (production only)
    cp -r includes/ "$release_dir/"
    cp -r user_files/ "$release_dir/"
    cp -r src/ "$release_dir/"
    cp -r public/ "$release_dir/"
    cp -r frontend/dist/ "$release_dir/frontend/"
    cp -r config/ "$release_dir/"
cp -r systemd/ "$release_dir/"
    
    # Configuration files
    cp composer.json "$release_dir/"
    cp composer.lock "$release_dir/"
    cp .htaccess "$release_dir/"
    cp public/.htaccess "$release_dir/public/" 2>/dev/null || true
    
    # Documentation
    cp README.md "$release_dir/" 2>/dev/null || true
    cp SECURITY.md "$release_dir/" 2>/dev/null || true
    cp CUSTOM_HEADER_BACKGROUND.md "$release_dir/" 2>/dev/null || true
    
    # Installation and security files
    cp install.sh "$release_dir/"
    cp -r sudoers.d/ "$release_dir/" 2>/dev/null || true
    cp -r systemd/ "$release_dir/" 2>/dev/null || true
    
    # Essential scripts only (exclude development scripts)
    mkdir -p "$release_dir/scripts"
    cp scripts/supermon_unified_file_editor.sh "$release_dir/scripts/"
    cp scripts/manage_users.php "$release_dir/scripts/"
    cp scripts/update.sh "$release_dir/scripts/"
    cp scripts/version-check.sh "$release_dir/scripts/"
    cp scripts/performance-report.sh "$release_dir/scripts/" 2>/dev/null || true
    
    # Static assets
    cp *.jpg "$release_dir/" 2>/dev/null || true
    cp *.png "$release_dir/" 2>/dev/null || true
    cp favicon.ico "$release_dir/" 2>/dev/null || true
    cp astdb.txt "$release_dir/" 2>/dev/null || true
    
    log "Excluding development files (node_modules, .git, logs, tests, etc.)"
    
    # Create release documentation
    log "Creating release documentation..."
    create_release_notes "$version" "$version_date" "$release_dir"
    create_install_guide "$release_dir"
    
    # Validate the release
    validate_release "$release_dir"
    
    # Create tarball
    log "Creating tarball: $release_file"
    cd /tmp
    tar -cJf "$release_file" "supermon-ng"
    
    # Create checksums
    create_checksums "$release_file" "/tmp"
    
    # Display results
    echo
    success "Release created successfully!"
    echo
    echo "📦 Release Package: $release_file"
    echo "📁 Size: $(du -h "$release_file" | cut -f1)"
    echo "🔍 SHA256: $(cat "$release_file.sha256")"
    echo
    echo "📋 Files included:"
    echo "   - Core application (src/, includes/, public/)"
    echo "   - Built frontend (frontend/dist/)"
    echo "   - User configuration files (user_files/)"
    echo "   - Performance optimization configs (config/)"
    echo "   - Installation script (install.sh)"
    echo "   - Update system (scripts/update.sh, scripts/version-check.sh)"
    echo "   - Security configurations (sudoers.d/, systemd/)"
    echo "   - Essential scripts (supermon_unified_file_editor.sh, manage_users.php)"
    echo "   - Documentation and checksums"
    echo ""
    echo "📋 Excluded development files:"
    echo "   - Source frontend (frontend/src/, node_modules/)"
    echo "   - Development scripts (backup-config.sh, dev-setup.sh, etc.)"
    echo "   - Test files and logs"
    echo "   - Git repository (.git/)"
    echo
    echo "🚀 To deploy:"
    echo "   tar -xJf $release_file"
    echo "   Follow instructions in INSTALL.md"
    echo
}

# Run main function
main "$@"
