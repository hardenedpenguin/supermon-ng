#!/bin/bash

# Supermon-NG Installation Script for ASL3+ Servers
# This script installs and configures Supermon-NG to work on any ASL3+ node

set -e

echo "🚀 Installing Supermon-NG on ASL3+ Server..."

# Check if we're running as root
if [ "$EUID" -ne 0 ]; then
    echo "❌ This script must be run as root (use sudo)"
    exit 1
fi

# Install dependencies
echo "📦 Installing system dependencies..."
apt-get update
apt-get install -y php php-sqlite3 php-curl php-mbstring git curl

# Install Node.js 20.x (required for Vite)
echo "📦 Installing Node.js 20.x..."
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt-get install -y nodejs

# Verify Node.js version
NODE_VERSION=$(node --version)
echo "✅ Node.js version: $NODE_VERSION"

# Install Composer via package manager
if ! command -v composer &> /dev/null; then
    echo "📦 Installing Composer..."
    apt-get install -y composer
fi

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

# Copy all files to the target directory (if not already there)
if [ "$(pwd)" != "$APP_DIR" ]; then
    echo "📁 Copying files to $APP_DIR..."
    cp -r . "$APP_DIR/"
    cd "$APP_DIR"
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

# Install Node.js dependencies and build frontend
echo "📦 Installing Node.js dependencies..."
cd "$APP_DIR/frontend"
if [ -f "package.json" ]; then
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
    
    echo "✅ Frontend built successfully"
else
    echo "❌ Error: frontend/package.json not found. Make sure all files were extracted properly."
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
    DocumentRoot $APP_DIR/frontend/dist
    
    # Serve static files from frontend/dist
    <Directory "$APP_DIR/frontend/dist">
        AllowOverride All
        Require all granted
    </Directory>
    
    # Proxy API requests to backend
    ProxyPreserveHost On
    ProxyPass /api http://localhost:8000/api
    ProxyPassReverse /api http://localhost:8000/api
    
    # Handle Vue router (SPA)
    <Directory "$APP_DIR/frontend/dist">
        RewriteEngine On
        RewriteBase /
        RewriteRule ^index\.html$ - [L]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
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
echo "4. Verify the configuration:"
echo "   sudo apache2ctl configtest"
echo ""

# Enable and start services
echo "🚀 Starting services..."
systemctl daemon-reload
systemctl enable supermon-ng-backend
systemctl start supermon-ng-backend

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
echo "   ✅ System dependencies installed"
echo "   ✅ PHP dependencies installed"
echo "   ✅ Node.js dependencies installed"
echo "   ✅ Frontend built"
echo "   ✅ Backend service created and started"
echo "   ⚠️  Apache configuration needs manual completion"
