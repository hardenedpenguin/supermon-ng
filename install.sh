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

# Install Node.js 20.x (required for Vite)
echo "📦 Installing Node.js 20.x..."
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt-get install -y nodejs

# Verify Node.js version
NODE_VERSION=$(node --version)
echo "✅ Node.js version: $NODE_VERSION"

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
mkdir -p "$APP_DIR/database"
mkdir -p "$APP_DIR/user_files"

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
    echo "⚠️  Unified file editor already exists. Backing up existing file..."
    cp "$EDITOR_SCRIPT" "$EDITOR_SCRIPT.backup.$(date +%Y%m%d_%H%M%S)"
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
    if [ -f "$EDITOR_SCRIPT.backup.$(date +%Y%m%d_%H%M%S)" ]; then
        echo "   Restoring backup..."
        mv "$EDITOR_SCRIPT.backup.$(date +%Y%m%d_%H%M%S)" "$EDITOR_SCRIPT"
    fi
    exit 1
fi

# Install sudoers configuration
echo "🔐 Installing sudoers configuration..."
SUDOERS_FILE="/etc/sudoers.d/011_www-nopasswd"
if [ -f "$SUDOERS_FILE" ]; then
    echo "⚠️  Sudoers file already exists. Backing up existing file..."
    cp "$SUDOERS_FILE" "$SUDOERS_FILE.backup.$(date +%Y%m%d_%H%M%S)"
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
    if [ -f "$SUDOERS_FILE.backup.$(date +%Y%m%d_%H%M%S)" ]; then
        echo "   Restoring backup..."
        mv "$SUDOERS_FILE.backup.$(date +%Y%m%d_%H%M%S)" "$SUDOERS_FILE"
    fi
    exit 1
fi

# Set proper permissions
chown -R www-data:www-data "$APP_DIR"
chmod -R 755 "$APP_DIR"
chmod -R 777 "$APP_DIR/logs"
chmod -R 777 "$APP_DIR/database"
chmod -R 777 "$APP_DIR/user_files"

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

# Create systemd service files
echo "🔧 Creating systemd services..."

# Backend service
SERVICE_FILE="/etc/systemd/system/supermon-ng-backend.service"
if [ -f "$SERVICE_FILE" ]; then
    echo "⚠️  Service file $SERVICE_FILE already exists. Skipping creation."
    echo "   If you want to update the service, please remove it manually first:"
    echo "   sudo rm $SERVICE_FILE"
else
    echo "📝 Creating backend service file..."
    cat > "$SERVICE_FILE" << EOF
[Unit]
Description=Supermon-NG Backend
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=$APP_DIR
ExecStart=/usr/bin/php -S localhost:8000 -t public public/index.php
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF
    echo "✅ Backend service file created"
fi

# Node Status service (optional)
NODE_STATUS_SERVICE_FILE="/etc/systemd/system/supermon-ng-node-status.service"
NODE_STATUS_TIMER_FILE="/etc/systemd/system/supermon-ng-node-status.timer"

if [ -f "$APP_DIR/user_files/sbin/node_info.ini" ]; then
    echo "📝 Installing node status service files..."
    
    if [ -f "$NODE_STATUS_SERVICE_FILE" ]; then
        echo "⚠️  Node status service file already exists. Skipping installation."
    else
        if [ -f "$INSTALLER_DIR/systemd/supermon-ng-node-status.service" ]; then
            # Update the service file with correct paths and copy it
            sed "s|WorkingDirectory=.*|WorkingDirectory=$APP_DIR/user_files/sbin|g; s|ExecStart=.*|ExecStart=/usr/bin/python3 $APP_DIR/user_files/sbin/ast_node_status_update.py|g; s|StandardOutput=.*|StandardOutput=append:$APP_DIR/logs/node-status-update.log|g; s|StandardError=.*|StandardError=append:$APP_DIR/logs/node-status-update.log|g" "$INSTALLER_DIR/systemd/supermon-ng-node-status.service" > "$NODE_STATUS_SERVICE_FILE"
            echo "✅ Node status service file installed"
        else
            echo "❌ Node status service template not found in systemd directory"
        fi
    fi
    
    if [ -f "$NODE_STATUS_TIMER_FILE" ]; then
        echo "⚠️  Node status timer file already exists. Skipping installation."
    else
        if [ -f "$INSTALLER_DIR/systemd/supermon-ng-node-status.timer" ]; then
            cp "$INSTALLER_DIR/systemd/supermon-ng-node-status.timer" "$NODE_STATUS_TIMER_FILE"
            echo "✅ Node status timer file installed"
        else
            echo "❌ Node status timer template not found in systemd directory"
        fi
    fi
else
    echo "ℹ️  Node status configuration not found. Skipping node status service setup."
    echo "   To enable node status updates, configure $APP_DIR/user_files/sbin/node_info.ini"
fi

# Install Apache if not present (unless skipping Apache configuration)
if [ "$SKIP_APACHE" = false ]; then
    if ! command -v apache2 &> /dev/null; then
        echo "📦 Installing Apache..."
        apt-get install -y apache2
    fi
else
    echo "⏭️  Skipping Apache installation (--skip-apache flag)"
fi

# Function to detect all IP addresses on the current machine
detect_ip_addresses() {
    echo "🔍 Detecting IP addresses on this machine..."
    
    # Get all IP addresses (excluding loopback and link-local)
    IP_ADDRESSES=()
    
    # Method 1: Using ip command (preferred)
    if command -v ip >/dev/null 2>&1; then
        while IFS= read -r ip; do
            # Skip loopback, link-local, and multicast addresses
            if [[ ! "$ip" =~ ^127\. ]] && [[ ! "$ip" =~ ^169\.254\. ]] && [[ ! "$ip" =~ ^224\. ]] && [[ ! "$ip" =~ ^::1$ ]] && [[ ! "$ip" =~ ^fe80: ]]; then
                IP_ADDRESSES+=("$ip")
            fi
        done < <(ip -o -4 addr show | awk '{print $4}' | cut -d'/' -f1)
        
        # Also get IPv6 addresses
        while IFS= read -r ip; do
            if [[ ! "$ip" =~ ^::1$ ]] && [[ ! "$ip" =~ ^fe80: ]]; then
                IP_ADDRESSES+=("$ip")
            fi
        done < <(ip -o -6 addr show | awk '{print $4}' | cut -d'/' -f1)
    fi
    
    # Method 2: Fallback to hostname command if ip command fails
    if [ ${#IP_ADDRESSES[@]} -eq 0 ] && command -v hostname >/dev/null 2>&1; then
        HOSTNAME_IPS=$(hostname -I 2>/dev/null || true)
        if [ -n "$HOSTNAME_IPS" ]; then
            while IFS= read -r ip; do
                if [[ ! "$ip" =~ ^127\. ]] && [[ ! "$ip" =~ ^169\.254\. ]]; then
                    IP_ADDRESSES+=("$ip")
                fi
            done <<< "$HOSTNAME_IPS"
        fi
    fi
    
    # Method 3: Fallback to ifconfig if available
    if [ ${#IP_ADDRESSES[@]} -eq 0 ] && command -v ifconfig >/dev/null 2>&1; then
        while IFS= read -r ip; do
            if [[ ! "$ip" =~ ^127\. ]] && [[ ! "$ip" =~ ^169\.254\. ]]; then
                IP_ADDRESSES+=("$ip")
            fi
        done < <(ifconfig 2>/dev/null | grep -oP 'inet \K[0-9.]+' || true)
    fi
    
    # Remove duplicates and sort
    if [ ${#IP_ADDRESSES[@]} -gt 0 ]; then
        # Remove duplicates using associative array
        declare -A unique_ips
        for ip in "${IP_ADDRESSES[@]}"; do
            unique_ips["$ip"]=1
        done
        IP_ADDRESSES=($(printf '%s\n' "${!unique_ips[@]}" | sort))
    fi
    
    # Display detected IPs
    if [ ${#IP_ADDRESSES[@]} -gt 0 ]; then
        echo "✅ Detected IP addresses:"
        for ip in "${IP_ADDRESSES[@]}"; do
            echo "   - $ip"
        done
    else
        echo "⚠️  No IP addresses detected, will use localhost only"
        IP_ADDRESSES=("127.0.0.1")
    fi
    
    echo ""
}

# Create Apache configuration template
APACHE_TEMPLATE="$APP_DIR/apache-config-template.conf"
if [ -f "$APACHE_TEMPLATE" ]; then
    echo "⚠️  Apache configuration template already exists. Skipping creation."
    echo "   If you want to update the template, please remove it manually first:"
    echo "   sudo rm $APACHE_TEMPLATE"
else
    # Detect IP addresses
    detect_ip_addresses
    
    echo "📝 Creating Apache configuration template with detected IP addresses..."
    
    # Generate ServerAlias entries
    SERVER_ALIASES=""
    if [ ${#IP_ADDRESSES[@]} -gt 0 ]; then
        for ip in "${IP_ADDRESSES[@]}"; do
            SERVER_ALIASES="${SERVER_ALIASES}    ServerAlias $ip"$'\n'
        done
    fi
    
    cat > "$APACHE_TEMPLATE" << EOF
# Supermon-NG Apache Configuration Template
# Copy this configuration to your Apache sites-available directory
# Generated with detected IP addresses as ServerAlias entries

<VirtualHost *:80>
    ServerName localhost
$SERVER_ALIASES    DocumentRoot /var/www/html
    
    # Proxy configurations (must come before Alias directives)
    ProxyPreserveHost On
    
    # Proxy supermon-ng API requests to backend (must come before Alias)
    ProxyPass /supermon-ng/api http://localhost:8000/api
    ProxyPassReverse /supermon-ng/api http://localhost:8000/api
    
    # Alias for Supermon-NG application (after ProxyPass)
    Alias /supermon-ng $APP_DIR/public
    
    # Alias for user files
    Alias /supermon-ng/user_files $APP_DIR/user_files
    
    # Proxy HamClock requests (adjust IP and port as needed)
    # Uncomment and modify the following lines if you have HamClock running:
    # ProxyPass /hamclock/ http://10.0.0.41:8082/
    # ProxyPassReverse /hamclock/ http://10.0.0.41:8082/
    # 
    # Proxy HamClock WebSocket connections for live updates
    # ProxyPass /live-ws ws://10.0.0.41:8082/live-ws
    # ProxyPassReverse /live-ws ws://10.0.0.41:8082/live-ws
    
    # Configure Supermon-NG directory
    <Directory "$APP_DIR/public">
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
    <Directory "$APP_DIR/user_files">
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
EOF
    echo "✅ Apache configuration template created"
fi

# Automatically install and configure Apache site (unless skipping)
if [ "$SKIP_APACHE" = false ]; then
    echo "🔧 Configuring Apache automatically..."
    APACHE_SITE_FILE="/etc/apache2/sites-available/supermon-ng.conf"

    # Enable required Apache modules
    echo "📦 Enabling required Apache modules..."
    a2enmod -q proxy proxy_http proxy_wstunnel rewrite headers 2>/dev/null || {
        echo "⚠️  Warning: Some Apache modules may not be available"
    }

    # Install the Apache site configuration
    if [ -f "$APACHE_SITE_FILE" ]; then
        echo "⚠️  Apache site configuration already exists. Backing up existing file..."
        cp "$APACHE_SITE_FILE" "$APACHE_SITE_FILE.backup.$(date +%Y%m%d_%H%M%S)"
    fi

    echo "📝 Installing Apache site configuration..."
    cp "$APACHE_TEMPLATE" "$APACHE_SITE_FILE"

    # Enable the site
    echo "🔗 Enabling Apache site..."
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

echo ""
if [ "$APACHE_AUTO_CONFIGURED" = true ]; then
    echo "✅ APACHE CONFIGURATION COMPLETED AUTOMATICALLY"
    echo "=============================================="
    echo "Apache has been configured and is running with the following access points:"
    echo "   - http://localhost"
    for ip in "${IP_ADDRESSES[@]}"; do
        echo "   - http://$ip"
    done
    echo ""
    echo "The site is now accessible via all detected IP addresses!"
elif [ "$SKIP_APACHE" = true ]; then
    echo "⏭️  APACHE CONFIGURATION SKIPPED"
    echo "==============================="
    echo "Apache configuration was skipped as requested (--skip-apache flag)."
    echo ""
    echo "The installation script has created a template configuration file at:"
    echo "   $APP_DIR/apache-config-template.conf"
    echo ""
    echo "To configure your web server later:"
    echo ""
    echo "1. For Apache users:"
    echo "   sudo a2enmod proxy proxy_http proxy_wstunnel rewrite headers"
    echo "   sudo cp $APP_DIR/apache-config-template.conf /etc/apache2/sites-available/supermon-ng.conf"
    echo "   sudo a2ensite supermon-ng"
    echo "   sudo systemctl restart apache2"
    echo ""
    echo "2. For Nginx users:"
    echo "   # Use the Apache template as reference for proxy configuration"
    echo "   # Configure proxy_pass to http://localhost:8000/api"
    echo "   # Configure static file serving from $APP_DIR/public"
    echo ""
    echo "3. For other web servers:"
    echo "   # Configure reverse proxy to http://localhost:8000/api"
    echo "   # Serve static files from $APP_DIR/public"
    echo "   # Handle Vue.js SPA routing (fallback to index.html)"
    echo ""
    echo "The backend service is running and ready to accept connections on port 8000."
else
    echo "⚠️  MANUAL APACHE CONFIGURATION REQUIRED"
    echo "========================================"
    echo "The installation script has created a template configuration file at:"
    echo "   $APP_DIR/apache-config-template.conf"
    echo ""
    echo "To complete the setup, you need to:"
    echo ""
    echo "1. Enable required Apache modules:"
    echo "   sudo a2enmod proxy"
    echo "   sudo a2enmod proxy_http"
    echo "   sudo a2enmod proxy_wstunnel"
    echo "   sudo a2enmod rewrite"
    echo ""
    echo "2. Copy the configuration template to Apache:"
    echo "   sudo cp $APP_DIR/apache-config-template.conf /etc/apache2/sites-available/supermon-ng.conf"
    echo ""
    echo "3. Enable the site and restart Apache:"
    echo "   sudo a2ensite supermon-ng"
    echo "   sudo a2dissite 000-default  # Optional: disable default site"
    echo "   sudo systemctl restart apache2"
    echo ""
    echo "4. If you have HamClock, edit the configuration to enable the proxy:"
    echo "   sudo nano /etc/apache2/sites-available/supermon-ng.conf"
    echo "   # Uncomment and modify the HamClock proxy lines with your server IP/port"
    echo ""
    echo "5. Verify the configuration:"
    echo "   sudo apache2ctl configtest"
    echo ""
fi

# Enable and start services
echo "🚀 Starting services..."
systemctl daemon-reload
systemctl enable supermon-ng-backend
systemctl start supermon-ng-backend

# Enable node status service if configured
if [ -f "$APP_DIR/user_files/sbin/node_info.ini" ]; then
    echo "🚀 Starting node status service..."
    systemctl enable supermon-ng-node-status.timer
    systemctl start supermon-ng-node-status.timer
    echo "✅ Node status service enabled and started"
fi

# Note about Apache configuration
if [ "$APACHE_AUTO_CONFIGURED" = true ]; then
    echo "📝 Note: Apache has been automatically configured and is ready to use."
else
    echo "📝 Note: Apache configuration must be completed manually as shown above."
    echo "   The backend service will start, but the web interface won't work until Apache is configured."
fi

# Make user management scripts executable
echo "🔧 Setting script permissions..."
if [ -f "$APP_DIR/user_files/set_password.sh" ]; then
    chmod +x "$APP_DIR/user_files/set_password.sh"
    echo "✅ Made executable: user_files/set_password.sh"
fi

if [ -f "$APP_DIR/scripts/manage_users.php" ]; then
    chmod +x "$APP_DIR/scripts/manage_users.php"
    echo "✅ Made executable: scripts/manage_users.php"
fi

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
    for ip in "${IP_ADDRESSES[@]}"; do
        echo "   - http://$ip"
    done
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
echo "   ✅ PHP dependencies installed"
echo "   ✅ Node.js dependencies installed"
echo "   ✅ Frontend built"
echo "   ✅ Backend service created and started"
if [ -f "$APP_DIR/user_files/sbin/node_info.ini" ]; then
    echo "   ✅ Node status service enabled and started"
fi
if [ "$APACHE_AUTO_CONFIGURED" = true ]; then
    echo "   ✅ Apache configuration completed automatically with IP aliases"
elif [ "$SKIP_APACHE" = true ]; then
    echo "   ⏭️  Apache configuration skipped (--skip-apache flag)"
else
    echo "   ⚠️  Apache configuration needs manual completion"
fi
