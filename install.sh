#!/bin/bash

# Supermon-NG Installation Script for ASL3+ Servers
# This script installs and configures Supermon-NG to work on any ASL3+ node

set -e

# System configuration
WWW_GROUP="www-data"
WWW_USER="www-data"
ASTERISK_LOG_DIR="/var/log/asterisk"
APACHE_LOG_DIR="/var/log/apache2"

echo "🚀 Installing Supermon-NG on ASL3+ Server..."

# Check if we're running as root
if [ "$EUID" -ne 0 ]; then
    echo "❌ This script must be run as root (use sudo)"
    exit 1
fi

# Check if this is an update or fresh installation
APP_DIR="${SUPERMON_INSTALL_DIR:-/var/www/html/supermon-ng}"
if [ -d "$APP_DIR" ] && [ -f "$APP_DIR/includes/common.inc" ]; then
    echo "📋 Existing Supermon-NG installation detected."
    echo "   For updates, please use: sudo ./scripts/update.sh"
    echo "   This script is for fresh installations only."
    echo ""
    read -p "Do you want to continue with a fresh installation? (This will overwrite existing files) [y/N]: " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Installation cancelled. Use ./scripts/update.sh for updates."
        exit 0
    fi
    echo "⚠️  Proceeding with fresh installation (existing files will be overwritten)"
fi

# Check for command line options
SKIP_APACHE=false
for arg in "$@"; do
    case $arg in
        --skip-apache)
            SKIP_APACHE=true
            echo "🔧 Apache configuration will be skipped (--skip-apache flag detected)"
            ;;
        --help|-h)
            echo "Supermon-NG Installation Script"
            echo ""
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --skip-apache    Skip automatic Apache configuration"
            echo "  --help, -h       Show this help message"
            echo ""
            echo "Examples:"
            echo "  $0                    # Normal installation with Apache setup"
            echo "  $0 --skip-apache      # Install without Apache configuration"
            echo ""
            echo "When using --skip-apache:"
            echo "  - You must manually configure your web server"
            echo "  - The backend service will still be installed and started"
            echo "  - Apache configuration template will still be created"
            echo "  - You can configure Apache later using the template"
            exit 0
            ;;
    esac
done

# Install dependencies
echo "📦 Installing system dependencies..."
apt-get update
apt-get install -y php php-sqlite3 php-curl php-mbstring php-xml git curl acl

# Install Node.js 20.x only when building frontend from source (git clone)
# Release tarballs include pre-built frontend; no Node.js/npm needed
if [ -f "frontend/package.json" ]; then
    echo "📦 Installing Node.js 20.x (required for building frontend from source)..."

    # Detect Debian version
    DEBIAN_VERSION=""
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        if [ "$ID" = "debian" ]; then
            DEBIAN_VERSION=$(echo "$VERSION_ID" | cut -d. -f1)
        fi
    fi

    # Install Node.js and npm based on Debian version
    if [ "$DEBIAN_VERSION" = "13" ]; then
        echo "   Using Debian 13 packages..."
        apt-get install -y nodejs npm
    elif [ "$DEBIAN_VERSION" = "12" ]; then
        echo "   Using NodeSource repository for Debian 12..."
        curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
        apt-get install -y nodejs
    else
        echo "   Using NodeSource repository (non-Debian or unknown version)..."
        curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
        apt-get install -y nodejs
    fi

    # Ensure npm is available (NodeSource bundles it; Debian may need separate package)
    if ! command -v npm &>/dev/null; then
        echo "   Installing npm..."
        apt-get install -y npm
    fi

    # Verify Node.js and npm
    NODE_VERSION=$(node --version)
    NPM_VERSION=$(npm --version)
    echo "✅ Node.js version: $NODE_VERSION"
    echo "✅ npm version: $NPM_VERSION"
else
    echo "⏭️  Skipping Node.js installation (using pre-built frontend from release)"
fi

# Function to configure log access using ACLs
configure_logs() {
    echo "🔐 Configuring log access with ACLs..."
    
    # Configure ACLs for Apache logs
    if [ -d "$APACHE_LOG_DIR" ] && command -v setfacl >/dev/null 2>&1; then
        echo "   📋 Configuring Apache log access..."
        setfacl -R -m "g:${WWW_GROUP}:rX" "$APACHE_LOG_DIR" 2>/dev/null || echo "   ⚠️  Warning: Failed to set Apache log ACLs"
        # Set default ACLs for future files
        setfacl -R -d -m "g:${WWW_GROUP}:rX" "$APACHE_LOG_DIR" 2>/dev/null || echo "   ⚠️  Warning: Failed to set Apache log default ACLs"
    else
        echo "   ⚠️  Warning: Apache log directory not found or setfacl not available"
    fi
    
    # Configure ACLs for Asterisk logs
    if [ -d "$ASTERISK_LOG_DIR" ] && command -v setfacl >/dev/null 2>&1; then
        echo "   📋 Configuring Asterisk log access..."
        setfacl -R -m "g:${WWW_GROUP}:rX" "$ASTERISK_LOG_DIR" 2>/dev/null || echo "   ⚠️  Warning: Failed to set Asterisk log ACLs"
        # Set default ACLs for future files
        setfacl -R -d -m "g:${WWW_GROUP}:rX" "$ASTERISK_LOG_DIR" 2>/dev/null || echo "   ⚠️  Warning: Failed to set Asterisk log default ACLs"
    else
        echo "   ⚠️  Warning: Asterisk log directory not found or setfacl not available"
    fi
    
    echo "✅ Log access configuration completed"
}

# Install Composer via package manager
if ! command -v composer &> /dev/null; then
    echo "📦 Installing Composer..."
    apt-get install -y composer
fi

# Configure log access with ACLs
configure_logs

# Set up the application directory
APP_DIR="${SUPERMON_INSTALL_DIR:-/var/www/html/supermon-ng}"
echo "📁 Setting up application in $APP_DIR"

# Check if we're in the right directory (should contain the extracted files)
if [ ! -f "install.sh" ] || [ ! -d "frontend" ] || [ ! -d "includes" ]; then
    echo "❌ Error: Please run this script from the extracted supermon-ng directory"
    echo "   Make sure you've extracted the tar.gz file and are in the supermon-ng folder"
    exit 1
fi

# Create necessary directories
mkdir -p "$APP_DIR/logs"
mkdir -p "$APP_DIR/user_files"
mkdir -p "$APP_DIR/cache"

# Store the original directory for accessing installer files
INSTALLER_DIR="$(pwd)"

# Copy all files to the target directory (if not already there)
if [ "$(pwd)" != "$APP_DIR" ]; then
    echo "📁 Copying files to $APP_DIR..."
    # Copy everything except installer files, documentation, sudoers.d directory, systemd directory and supermon_unified_file_editor.sh
    find . -maxdepth 1 ! -name . ! -name "*.md" ! -name "install.sh" ! -name sudoers.d ! -name systemd ! -name scripts -exec cp -r {} "$APP_DIR/" \;
    # Copy scripts directory but exclude supermon_unified_file_editor.sh
    if [ -d "scripts" ]; then
        mkdir -p "$APP_DIR/scripts"
        find scripts -name "*.php" -exec cp {} "$APP_DIR/scripts/" \;
        find scripts -name "*.sh" ! -name "supermon_unified_file_editor.sh" -exec cp {} "$APP_DIR/scripts/" \;
    fi
    # Explicitly copy astdb.txt if it exists
    if [ -f "astdb.txt" ]; then
        echo "📄 Copying astdb.txt..."
        cp astdb.txt "$APP_DIR/"
    fi
    # Explicitly copy bin directory if it exists (WebSocket server)
    if [ -d "bin" ]; then
        echo "📄 Copying bin directory..."
        cp -r bin "$APP_DIR/"
    fi
    cd "$APP_DIR"
fi

# Verify astdb.txt was installed
if [ -f "$APP_DIR/astdb.txt" ]; then
    echo "✅ Asterisk database template (astdb.txt) installed successfully"
else
    echo "⚠️  Warning: astdb.txt not found in installation directory"
fi

# Install unified file editor script
echo "📝 Installing unified file editor script..."
EDITOR_SCRIPT="${SUPERMON_EDITOR_SCRIPT:-/usr/local/sbin/supermon_unified_file_editor.sh}"
if [ -f "$EDITOR_SCRIPT" ]; then
    echo "⚠️  Unified file editor already exists. Overwriting existing file..."
fi

# Copy and set proper permissions for editor script
cp "$INSTALLER_DIR/scripts/supermon_unified_file_editor.sh" "$EDITOR_SCRIPT"
chown root:root "$EDITOR_SCRIPT"
chmod 755 "$EDITOR_SCRIPT"

# Test the script syntax
if bash -n "$EDITOR_SCRIPT"; then
    echo "✅ Unified file editor installed and validated"
else
    echo "❌ Error: Invalid script syntax. Removing file..."
    rm "$EDITOR_SCRIPT"
    exit 1
fi

# Install sudoers configuration
echo "🔐 Installing sudoers configuration..."
SUDOERS_FILE="/etc/sudoers.d/011_www-nopasswd"
if [ -f "$SUDOERS_FILE" ]; then
    echo "⚠️  Sudoers file already exists. Overwriting existing file..."
fi

# Copy and set proper permissions for sudoers file
cp "$INSTALLER_DIR/sudoers.d/011_www-nopasswd" "$SUDOERS_FILE"
chown root:root "$SUDOERS_FILE"
chmod 440 "$SUDOERS_FILE"

# Validate sudoers syntax
if visudo -c -f "$SUDOERS_FILE"; then
    echo "✅ Sudoers configuration installed and validated"
else
    echo "❌ Error: Invalid sudoers syntax. Removing file..."
    rm "$SUDOERS_FILE"
    exit 1
fi

# Set proper permissions
chown -R www-data:www-data "$APP_DIR"
chmod -R 755 "$APP_DIR"
chmod -R 755 "$APP_DIR/logs"
chmod -R 755 "$APP_DIR/cache"

# user_files: 644 for config files, 755 only for executable scripts in sbin
find "$APP_DIR/user_files" -type d -exec chmod 755 {} \; 2>/dev/null || true
find "$APP_DIR/user_files" -type f -exec chmod 644 {} \; 2>/dev/null || true
chmod 644 "$APP_DIR/user_files/.htaccess" 2>/dev/null || true
chmod 644 "$APP_DIR/user_files/.htpasswd" 2>/dev/null || true
for script in ast_node_status_update.py din ssinfo; do
    [ -f "$APP_DIR/user_files/sbin/$script" ] && chmod 755 "$APP_DIR/user_files/sbin/$script"
done

# Install PHP dependencies
echo "📦 Installing PHP dependencies..."
cd "$APP_DIR"
if [ -f "composer.json" ]; then
    # Run composer as www-data user to avoid security warnings
    sudo -u www-data composer install --no-dev --optimize-autoloader
else
    echo "❌ Error: composer.json not found. Make sure all files were extracted properly."
    exit 1
fi

# Replace shipped example allmon.ini with one built from rpt.conf + manager.conf (requires root to read /etc/asterisk).
# Existing allmon.ini is backed up to allmon.ini.bak.<timestamp> when present (--force).
if [ -f "$APP_DIR/scripts/generate_local_allmon.php" ]; then
    echo "📋 Local Allmon: generating user_files/allmon.ini from Asterisk config (replaces example template)..."
    php "$APP_DIR/scripts/generate_local_allmon.php" --force || echo "   ⚠️  Generation failed; keeping existing user_files/allmon.ini. Fix Asterisk config or edit allmon.ini manually. Retry: sudo php $APP_DIR/scripts/generate_local_allmon.php --force"
    if [ -f "$APP_DIR/user_files/allmon.ini" ]; then
        chown www-data:www-data "$APP_DIR/user_files/allmon.ini" 2>/dev/null || true
        chmod 644 "$APP_DIR/user_files/allmon.ini" 2>/dev/null || true
    fi
fi

# Handle frontend installation (development vs production)
echo "📦 Setting up frontend..."
cd "$APP_DIR/frontend"

if [ -f "package.json" ]; then
    # Development installation - build from source
    echo "🔨 Development mode: Building frontend from source..."
    
    # Check Node.js version before building
    NODE_VERSION=$(node --version | sed 's/v//')
    REQUIRED_VERSION="20.19"
    
    if [ "$(printf '%s\n' "$REQUIRED_VERSION" "$NODE_VERSION" | sort -V | head -n1)" != "$REQUIRED_VERSION" ]; then
        echo "❌ Error: Node.js version $NODE_VERSION is too old. Vite requires Node.js 20.19+ or 22.12+"
        echo "   Current version: $NODE_VERSION"
        echo "   Required version: 20.19+ or 22.12+"
        exit 1
    fi
    
    echo "✅ Node.js version $NODE_VERSION is compatible"
    
    # Clean install to ensure fresh dependencies
    echo "🧹 Cleaning previous installation..."
    rm -rf node_modules package-lock.json
    
    # Install dependencies
    echo "📦 Installing dependencies..."
    npm install
    
    # Clean build directory
    echo "🧹 Cleaning build directory..."
    rm -rf dist
    
    # Build frontend
    echo "🔨 Building frontend..."
    npm run build
    
    # Copy built files to public directory
    echo "📁 Copying built frontend to public directory..."
    cp -r dist/* "$APP_DIR/public/"
    
    echo "✅ Frontend built and copied to public directory"
    
elif [ -d "dist" ] && [ -f "dist/index.html" ]; then
    # Production installation - pre-built frontend in dist/
    echo "📦 Production mode: Using pre-built frontend from dist/..."
    echo "📁 Copying frontend files to public directory..."
    
    # Copy frontend files to public directory where they should be served from
    cp -r dist/* "$APP_DIR/public/"
    
    echo "✅ Pre-built frontend copied to public directory"
    
elif [ -f "index.html" ] && [ -d "assets" ]; then
    # Production installation - pre-built frontend in root (release tarball)
    echo "📦 Production mode: Using pre-built frontend (release package)..."
    echo "📁 Copying frontend files to public directory..."
    
    # Copy frontend files to public directory where they should be served from
    cp -r * "$APP_DIR/public/"
    
    echo "✅ Pre-built frontend copied to public directory"
    
else
    echo "❌ Error: No valid frontend installation found."
    echo "   This indicates the installation files may be incomplete."
    echo "   Expected one of:"
    echo "   - Development: frontend/package.json (for building from source)"
    echo "   - Production: frontend/dist/index.html (pre-built in dist/)"
    echo "   - Release: frontend/index.html + frontend/assets/ (release package)"
    exit 1
fi

# Install systemd service files
echo "🔧 Installing systemd services..."

# Function to install systemd file from repository
install_systemd_file() {
    local SOURCE_FILE="$1"
    local TARGET_FILE="$2"
    local FILE_TYPE="$3"  # "service" or "timer"
    
    if [ ! -f "$SOURCE_FILE" ]; then
        echo "❌ Error: Source file $SOURCE_FILE not found"
        return 1
    fi
    
    if [ -f "$TARGET_FILE" ]; then
        echo "⚠️  $FILE_TYPE file $TARGET_FILE already exists. Overwriting existing file..."
    fi
    
    echo "📝 Installing $FILE_TYPE file from $SOURCE_FILE..."
    cp "$SOURCE_FILE" "$TARGET_FILE"
    
    # Replace placeholder with actual path
    sed -i "s|APP_DIR_PLACEHOLDER|$APP_DIR|g" "$TARGET_FILE"
    
    # Set proper permissions (644 for systemd files)
    chmod 644 "$TARGET_FILE"
    chown root:root "$TARGET_FILE"
    
    echo "✅ $FILE_TYPE file installed: $(basename $TARGET_FILE)"
}

# Backend service (copy from systemd directory)
install_systemd_file \
    "$INSTALLER_DIR/systemd/supermon-ng-backend.service" \
    "/etc/systemd/system/supermon-ng-backend.service" \
    "Service"

# WebSocket service (copy from systemd directory)
install_systemd_file \
    "$INSTALLER_DIR/systemd/supermon-ng-websocket.service" \
    "/etc/systemd/system/supermon-ng-websocket.service" \
    "Service"

# Database update service (copy from systemd directory)
install_systemd_file \
    "$INSTALLER_DIR/systemd/supermon-ng-database-update.service" \
    "/etc/systemd/system/supermon-ng-database-update.service" \
    "Service"

# Database update timer (copy from systemd directory)
install_systemd_file \
    "$INSTALLER_DIR/systemd/supermon-ng-database-update.timer" \
    "/etc/systemd/system/supermon-ng-database-update.timer" \
    "Timer"

# Node status update service (copy from systemd directory)
install_systemd_file \
    "$INSTALLER_DIR/systemd/supermon-ng-node-status.service" \
    "/etc/systemd/system/supermon-ng-node-status.service" \
    "Service"

# Node status update timer (copy from systemd directory)
install_systemd_file \
    "$INSTALLER_DIR/systemd/supermon-ng-node-status.timer" \
    "/etc/systemd/system/supermon-ng-node-status.timer" \
    "Timer"

# Install Apache and ssl-cert if not present (unless skipping Apache configuration)
if [ "$SKIP_APACHE" = false ]; then
    if ! command -v apache2 &> /dev/null; then
        echo "📦 Installing Apache..."
        apt-get install -y apache2 ssl-cert
    elif [ ! -f /etc/ssl/certs/ssl-cert-snakeoil.pem ]; then
        echo "📦 Installing ssl-cert (system self-signed certificate)..."
        apt-get install -y ssl-cert
    fi
else
    echo "⏭️  Skipping Apache installation (--skip-apache flag)"
fi

# Create Apache configuration template
APACHE_TEMPLATE="$APP_DIR/apache-config-template.conf"
if [ -f "$APACHE_TEMPLATE" ]; then
    echo "⚠️  Apache configuration template already exists. Skipping creation."
    echo "   If you want to update the template, please remove it manually first:"
    echo "   sudo rm $APACHE_TEMPLATE"
else
    echo "📝 Creating Apache configuration template..."
    
    cat > "$APACHE_TEMPLATE" << APACHE_EOF
# Supermon-NG Apache Configuration Template
# Copy this configuration to your Apache sites-available directory

<VirtualHost *:80>
    DocumentRoot /var/www/html
    
    # Proxy configurations (must come before Alias directives)
    ProxyPreserveHost On
    
    # Proxy supermon-ng API requests to backend (must come before Alias)
    ProxyPass /supermon-ng/api http://localhost:8000/api
    ProxyPassReverse /supermon-ng/api http://localhost:8000/api
    
    # WebSocket proxy for Supermon-NG nodes (must come before Alias directives)
    # All WebSocket connections route to the single router server on port 8105
    # The router extracts the node ID from the path and routes internally
    # MUST use RewriteRule with [P] flag for WebSocket proxying to work correctly
    # CRITICAL: The $1 in the target URL preserves the node ID from the path
    # Without $1, connections will fail with "no valid node ID in path" errors
    RewriteEngine On
    RewriteCond %{HTTP:Upgrade} =websocket [NC]
    RewriteCond %{HTTP:Connection} =Upgrade [NC]
    RewriteRule ^/supermon-ng/ws/(.+)$ ws://localhost:8105/supermon-ng/ws/\$1 [P,L]
    ProxyPassReverse /supermon-ng/ws/ ws://localhost:8105/supermon-ng/ws/
    
    # Alias for Supermon-NG application (after ProxyPass)
    Alias /supermon-ng APP_DIR_PLACEHOLDER/public
    
    # Alias for user files
    Alias /supermon-ng/user_files APP_DIR_PLACEHOLDER/user_files
    
    # Configure Supermon-NG directory
    <Directory "APP_DIR_PLACEHOLDER/public">
        AllowOverride All
        Require all granted
        
        # Ensure index.html is served by default (Vue.js frontend)
        DirectoryIndex index.html index.php
        
        # Handle Vue router (SPA) - rewrite all requests to index.html
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^ index.html [QSA,L]
    </Directory>
    
    # Configure user files directory
    <Directory "APP_DIR_PLACEHOLDER/user_files">
        AllowOverride All
        Require all granted
    </Directory>
    
    # Configure main document root
    <Directory "/var/www/html">
        AllowOverride All
        Require all granted
        Options Indexes FollowSymLinks
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/supermon-ng_error.log
    CustomLog \${APACHE_LOG_DIR}/supermon-ng_access.log combined
</VirtualHost>

# HTTPS VirtualHost using system self-signed certificate (Debian/Ubuntu ssl-cert-snakeoil)
<VirtualHost *:443>
    DocumentRoot /var/www/html
    
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/ssl-cert-snakeoil.pem
    SSLCertificateKeyFile /etc/ssl/private/ssl-cert-snakeoil.key
    
    # Proxy configurations (must come before Alias directives)
    ProxyPreserveHost On
    
    # Proxy supermon-ng API requests to backend (must come before Alias)
    ProxyPass /supermon-ng/api http://localhost:8000/api
    ProxyPassReverse /supermon-ng/api http://localhost:8000/api
    
    # WebSocket proxy for Supermon-NG nodes (must come before Alias directives)
    RewriteEngine On
    RewriteCond %{HTTP:Upgrade} =websocket [NC]
    RewriteCond %{HTTP:Connection} =Upgrade [NC]
    RewriteRule ^/supermon-ng/ws/(.+)$ ws://localhost:8105/supermon-ng/ws/\$1 [P,L]
    ProxyPassReverse /supermon-ng/ws/ ws://localhost:8105/supermon-ng/ws/
    
    # Alias for Supermon-NG application (after ProxyPass)
    Alias /supermon-ng APP_DIR_PLACEHOLDER/public
    
    # Alias for user files
    Alias /supermon-ng/user_files APP_DIR_PLACEHOLDER/user_files
    
    # Configure Supermon-NG directory
    <Directory "APP_DIR_PLACEHOLDER/public">
        AllowOverride All
        Require all granted
        DirectoryIndex index.html index.php
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^ index.html [QSA,L]
    </Directory>
    
    # Configure user files directory
    <Directory "APP_DIR_PLACEHOLDER/user_files">
        AllowOverride All
        Require all granted
    </Directory>
    
    # Configure main document root
    <Directory "/var/www/html">
        AllowOverride All
        Require all granted
        Options Indexes FollowSymLinks
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/supermon-ng_ssl_error.log
    CustomLog \${APACHE_LOG_DIR}/supermon-ng_ssl_access.log combined
</VirtualHost>
APACHE_EOF
    # Replace placeholder with actual path
    sed -i "s|APP_DIR_PLACEHOLDER|$APP_DIR|g" "$APACHE_TEMPLATE"
    echo "✅ Apache configuration template created"
fi

# Automatically install and configure Apache site (unless skipping)
if [ "$SKIP_APACHE" = false ]; then
    echo "🔧 Configuring Apache automatically..."
    APACHE_SITE_FILE="/etc/apache2/sites-available/supermon-ng.conf"

    # Enable required Apache modules (including mod_ssl for HTTPS)
    echo "📦 Enabling required Apache modules..."
    a2enmod -q proxy proxy_http proxy_wstunnel rewrite headers substitute ssl 2>/dev/null || {
        echo "⚠️  Warning: Some Apache modules may not be available"
    }

    # Install the Apache site configuration
    if [ -f "$APACHE_SITE_FILE" ]; then
        echo "⚠️  Apache site configuration already exists. Overwriting existing file..."
    fi

    echo "📝 Installing Apache site configuration..."
    cp "$APACHE_TEMPLATE" "$APACHE_SITE_FILE"

    # Disable the default sites so supermon-ng handles both :80 and :443
    echo "🔗 Disabling default Apache sites..."
    a2dissite -q 000-default 2>/dev/null || {
        echo "⚠️  Warning: Failed to disable default site (may not exist)"
    }
    a2dissite -q default-ssl 2>/dev/null || {
        echo "⚠️  Warning: Failed to disable default SSL site (may not exist)"
    }
    
    # Enable the supermon-ng site
    echo "🔗 Enabling supermon-ng Apache site..."
    a2ensite -q supermon-ng 2>/dev/null || {
        echo "⚠️  Warning: Failed to enable Apache site automatically"
    }

    # Test Apache configuration
    echo "🧪 Testing Apache configuration..."
    if apache2ctl configtest >/dev/null 2>&1; then
        echo "✅ Apache configuration test passed"
        
        # Restart Apache
        echo "🔄 Restarting Apache..."
        systemctl restart apache2
        
        if systemctl is-active apache2 >/dev/null 2>&1; then
            echo "✅ Apache restarted successfully"
            APACHE_AUTO_CONFIGURED=true
        else
            echo "❌ Apache failed to restart"
            APACHE_AUTO_CONFIGURED=false
        fi
    else
        echo "❌ Apache configuration test failed"
        echo "   Please check the configuration manually:"
        echo "   sudo apache2ctl configtest"
        APACHE_AUTO_CONFIGURED=false
    fi
else
    echo "⏭️  Skipping Apache configuration (--skip-apache flag)"
    APACHE_AUTO_CONFIGURED=false
fi

# Reload systemd and enable units (services are started at the end after everything is installed)
echo "🔧 Enabling systemd units..."
systemctl daemon-reload
systemctl enable supermon-ng-backend 2>/dev/null || true
if systemctl list-unit-files 2>/dev/null | grep -q "supermon-ng-websocket.service"; then
    systemctl enable supermon-ng-websocket.service 2>/dev/null || true
fi
systemctl enable supermon-ng-node-status.timer 2>/dev/null || true
systemctl enable supermon-ng-database-update.timer 2>/dev/null || true
echo "✅ Service files installed and enabled"

# Make user management scripts executable
echo "🔧 Setting script permissions..."
if [ -f "$APP_DIR/scripts/manage_users.php" ]; then
    chmod +x "$APP_DIR/scripts/manage_users.php"
    echo "✅ Made executable: scripts/manage_users.php"
fi

if [ -f "$APP_DIR/scripts/database-auto-update.php" ]; then
    chmod +x "$APP_DIR/scripts/database-auto-update.php"
    echo "✅ Made executable: scripts/database-auto-update.php"
fi

# Start services after everything is installed
echo ""
echo "🚀 Starting services..."
systemctl daemon-reload
systemctl start supermon-ng-backend 2>/dev/null || true
if systemctl list-unit-files 2>/dev/null | grep -q "supermon-ng-websocket.service"; then
    systemctl start supermon-ng-websocket.service 2>/dev/null || true
    echo "✅ WebSocket service started"
fi
systemctl start supermon-ng-node-status.timer 2>/dev/null || true
systemctl start supermon-ng-database-update.timer 2>/dev/null || true
echo "✅ Services started"

echo ""
echo "🎉 Supermon-NG Installation Complete!"
echo ""
echo "📊 Status:"
systemctl is-active supermon-ng-backend > /dev/null && echo "✅ Backend: Running" || echo "❌ Backend: Failed"
systemctl is-active apache2 > /dev/null && echo "✅ Apache: Running" || echo "❌ Apache: Failed"
echo ""
echo "🌐 Access your Supermon-NG application at:"
if [ "$APACHE_AUTO_CONFIGURED" = true ]; then
    echo "   - http://localhost"
    echo "   - http://your-server-ip"
    echo "   - http://your-domain.com"
    echo ""
    echo "✅ Apache is automatically configured and ready to use!"
elif [ "$SKIP_APACHE" = true ]; then
    echo "   Backend API: http://localhost:8000/api"
    echo "   (Configure your web server to proxy to this backend)"
    echo ""
    echo "⏭️  Apache configuration was skipped. Configure your web server manually."
else
    echo "   http://$(hostname -I | awk '{print $1}')"
    echo ""
    echo "⚠️  IMPORTANT: Complete Apache configuration manually as shown above!"
fi
echo ""
echo "🔧 Service Management:"
echo "   Start:  sudo systemctl start supermon-ng-backend"
echo "   Stop:   sudo systemctl stop supermon-ng-backend"
echo "   Status: sudo systemctl status supermon-ng-backend"
echo ""
echo "   After editing allmon.ini or username-allmon.ini, restart the WebSocket service"
echo "   so it picks up node changes: sudo systemctl restart supermon-ng-websocket.service"
echo ""
echo "⏰ Scheduled Tasks:"
systemctl is-active supermon-ng-node-status.timer > /dev/null 2>&1 && echo "   ✅ Node Status Updates: Every 3 minutes" || echo "   ⚠️  Node Status Updates: Not configured"
systemctl is-active supermon-ng-database-update.timer > /dev/null 2>&1 && echo "   ✅ Database Updates: Every 3 hours" || echo "   ⚠️  Database Updates: Not configured"
echo ""
echo "📝 Next steps:"
if [ "$APACHE_AUTO_CONFIGURED" = true ]; then
    echo "   1. Configure your AMI settings in $APP_DIR/user_files/"
    echo "   2. Set up your node configurations"
    echo "   3. Access the web interface to complete setup"
else
    echo "   1. Complete Apache configuration (see instructions above)"
    echo "   2. Configure your AMI settings in $APP_DIR/user_files/"
    echo "   3. Set up your node configurations"
    echo "   4. Access the web interface to complete setup"
fi

echo ""
echo "🔄 Future Updates:"
echo "   To update Supermon-NG to a newer version:"
echo "   1. Download the new version package"
echo "   2. Extract it to a temporary directory"
echo "   3. Run: sudo ./scripts/update.sh"
echo "   The update script will:"
echo "   - Preserve your user configurations when possible"
echo "   - Only advise about user_files changes when configs actually change"
echo "   - Create backups before making changes"
echo "   - Handle configuration migrations automatically"
echo ""
echo "📋 Installation Summary:"
echo "   ✅ System dependencies installed (including ACL support)"
echo "   ✅ Log access configured with ACLs"
echo "   ✅ Unified file editor installed and validated"
echo "   ✅ Sudoers configuration installed and validated"
echo "   ✅ PHP dependencies installed (including Doctrine DBAL)"
echo "   ✅ Node.js dependencies installed"
echo "   ✅ Frontend built with ASTDB optimizations"
echo "   ✅ Backend service created and started"
echo "   ✅ ASTDB cache system configured"
echo "   ✅ Database optimization layer ready"
if [ "$APACHE_AUTO_CONFIGURED" = true ]; then
    echo "   ✅ Apache configuration completed automatically with IP aliases"
elif [ "$SKIP_APACHE" = true ]; then
    echo "   ⏭️  Apache configuration skipped (--skip-apache flag)"
else
    echo "   ⚠️  Apache configuration needs manual completion"
fi
