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

# Install dependencies
echo "📦 Installing system dependencies..."
apt-get update
apt-get install -y php php-sqlite3 php-curl php-mbstring git curl acl

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
APP_DIR="/var/www/html/supermon-ng"
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
    # Copy everything except installer files, documentation, sudoers.d directory and supermon_unified_file_editor.sh
    find . -maxdepth 1 ! -name . ! -name "*.md" ! -name "install.sh" ! -name sudoers.d ! -name scripts -exec cp -r {} "$APP_DIR/" \;
    # Copy scripts directory but exclude supermon_unified_file_editor.sh
    if [ -d "scripts" ]; then
        mkdir -p "$APP_DIR/scripts"
        find scripts -name "*.php" -exec cp {} "$APP_DIR/scripts/" \;
        find scripts -name "*.sh" ! -name "supermon_unified_file_editor.sh" -exec cp {} "$APP_DIR/scripts/" \;
    fi
    cd "$APP_DIR"
fi

# Install unified file editor script
echo "📝 Installing unified file editor script..."
EDITOR_SCRIPT="/usr/local/sbin/supermon_unified_file_editor.sh"
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
    composer install --no-dev --optimize-autoloader
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
    echo "📝 Creating node status service files..."
    
    if [ -f "$NODE_STATUS_SERVICE_FILE" ]; then
        echo "⚠️  Node status service file already exists. Skipping creation."
    else
        cat > "$NODE_STATUS_SERVICE_FILE" << EOF
[Unit]
Description=Supermon-NG Node Status Update Service
After=network.target asterisk.service
Wants=asterisk.service

[Service]
Type=oneshot
User=root
WorkingDirectory=$APP_DIR/user_files/sbin
ExecStart=/usr/bin/python3 $APP_DIR/user_files/sbin/ast_node_status_update.py
StandardOutput=append:$APP_DIR/logs/node-status-update.log
StandardError=append:$APP_DIR/logs/node-status-update.log

[Install]
WantedBy=multi-user.target
EOF
        echo "✅ Node status service file created"
    fi
    
    if [ -f "$NODE_STATUS_TIMER_FILE" ]; then
        echo "⚠️  Node status timer file already exists. Skipping creation."
    else
        cat > "$NODE_STATUS_TIMER_FILE" << EOF
[Unit]
Description=Run Supermon-NG Node Status Update every 3 minutes
Requires=supermon-ng-node-status.service

[Timer]
OnBootSec=2min
OnUnitActiveSec=3min
AccuracySec=30s

[Install]
WantedBy=timers.target
EOF
        echo "✅ Node status timer file created"
    fi
else
    echo "ℹ️  Node status configuration not found. Skipping node status service setup."
    echo "   To enable node status updates, configure $APP_DIR/user_files/sbin/node_info.ini"
fi

# Install Apache if not present
if ! command -v apache2 &> /dev/null; then
    echo "📦 Installing Apache..."
    apt-get install -y apache2
fi

# Create Apache configuration template
APACHE_TEMPLATE="$APP_DIR/apache-config-template.conf"
if [ -f "$APACHE_TEMPLATE" ]; then
    echo "⚠️  Apache configuration template already exists. Skipping creation."
    echo "   If you want to update the template, please remove it manually first:"
    echo "   sudo rm $APACHE_TEMPLATE"
else
    echo "📝 Creating Apache configuration template..."
    cat > "$APACHE_TEMPLATE" << EOF
# Supermon-NG Apache Configuration Template
# Copy this configuration to your Apache sites-available directory

<VirtualHost *:80>
    ServerName localhost
    DocumentRoot $APP_DIR/public
    
    # Proxy configurations (must come before Directory blocks)
    ProxyPreserveHost On
    
    # Proxy API requests to backend
    ProxyPass /api http://localhost:8000/api
    ProxyPassReverse /api http://localhost:8000/api
    
    # Proxy HamClock requests (adjust IP and port as needed)
    # Uncomment and modify the following lines if you have HamClock running:
    # ProxyPass /hamclock/ http://10.0.0.41:8082/
    # ProxyPassReverse /hamclock/ http://10.0.0.41:8082/
    # 
    # Proxy HamClock WebSocket connections for live updates
    # ProxyPass /live-ws ws://10.0.0.41:8082/live-ws
    # ProxyPassReverse /live-ws ws://10.0.0.41:8082/live-ws
    
    # Serve static files from public directory
    <Directory "$APP_DIR/public">
        AllowOverride All
        Require all granted
        
        # Handle Vue router (SPA) with exclusions for proxy paths
        RewriteEngine On
        RewriteBase /
        RewriteRule ^index\.html$ - [L]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_URI} !^/api/
        RewriteCond %{REQUEST_URI} !^/hamclock/
        RewriteRule . /index.html [L]
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/supermon-ng_error.log
    CustomLog \${APACHE_LOG_DIR}/supermon-ng_access.log combined
</VirtualHost>
EOF
    echo "✅ Apache configuration template created"
fi

echo ""
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

# Note: Apache configuration must be done manually
echo "📝 Note: Apache configuration must be completed manually as shown above."
echo "   The backend service will start, but the web interface won't work until Apache is configured."

# Create management scripts
echo "📝 Creating management scripts..."

# Start script
START_SCRIPT="$APP_DIR/start.sh"
if [ -f "$START_SCRIPT" ]; then
    echo "⚠️  Start script already exists. Skipping creation."
else
    echo "📝 Creating start script..."
    cat > "$START_SCRIPT" << 'EOF'
#!/bin/bash
systemctl start supermon-ng-backend
echo "✅ Supermon-NG backend started"
echo "🌐 Access: http://$(hostname -I | awk '{print $1}')"
echo "📝 Note: Make sure Apache is configured and running for web access"
EOF
    echo "✅ Start script created"
fi

# Stop script
STOP_SCRIPT="$APP_DIR/stop.sh"
if [ -f "$STOP_SCRIPT" ]; then
    echo "⚠️  Stop script already exists. Skipping creation."
else
    echo "📝 Creating stop script..."
    cat > "$STOP_SCRIPT" << 'EOF'
#!/bin/bash
systemctl stop supermon-ng-backend
echo "🛑 Supermon-NG stopped"
EOF
    echo "✅ Stop script created"
fi

# Status script
STATUS_SCRIPT="$APP_DIR/status.sh"
if [ -f "$STATUS_SCRIPT" ]; then
    echo "⚠️  Status script already exists. Skipping creation."
else
    echo "📝 Creating status script..."
    cat > "$STATUS_SCRIPT" << 'EOF'
#!/bin/bash
echo "📊 Supermon-NG Status:"
echo "Backend: $(systemctl is-active supermon-ng-backend)"
echo "Apache: $(systemctl is-active apache2)"
echo ""
echo "🌐 Access URL:"
echo "http://$(hostname -I | awk '{print $1}')"
EOF
    echo "✅ Status script created"
fi

# Make scripts executable (only if they exist)
echo "🔧 Setting script permissions..."
for script in "$APP_DIR"/*.sh; do
    if [ -f "$script" ]; then
        chmod +x "$script"
        echo "✅ Made executable: $(basename "$script")"
    fi
done

# Make user management scripts executable
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
echo "   http://$(hostname -I | awk '{print $1}')"
echo ""
echo "⚠️  IMPORTANT: Complete Apache configuration manually as shown above!"
echo ""
echo "🔧 Management commands:"
echo "   Start:  $APP_DIR/start.sh"
echo "   Stop:   $APP_DIR/stop.sh"
echo "   Status: $APP_DIR/status.sh"
echo ""
echo "📝 Next steps:"
echo "   1. Complete Apache configuration (see instructions above)"
echo "   2. Configure your AMI settings in $APP_DIR/user_files/"
echo "   3. Set up your node configurations"
echo "   4. Access the web interface to complete setup"
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
echo "   ⚠️  Apache configuration needs manual completion"
