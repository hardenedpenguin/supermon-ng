#!/bin/bash

# Supermon-NG Update Script
# This script handles version updates while preserving user configurations
# Only advises about user_files changes when configuration structure actually changes

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
APP_DIR="/var/www/html/supermon-ng"

# Version information
CURRENT_VERSION=""
NEW_VERSION=""
VERSION_DATE=""

# Configuration change tracking
CONFIG_CHANGED=false
USER_FILES_BACKUP_DIR=""

echo -e "${BLUE}Supermon-NG Update Script${NC}"
echo "=============================="

# Function to print status messages
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check for command line options
SKIP_APACHE=false
FORCE_UPDATE=false
for arg in "$@"; do
    case $arg in
        --skip-apache)
            SKIP_APACHE=true
            print_status "Apache configuration will be skipped (--skip-apache flag detected)"
            ;;
        --force|-f)
            FORCE_UPDATE=true
            print_status "Force update enabled - will update even if versions match (--force flag detected)"
            ;;
        --help|-h)
            echo "Supermon-NG Update Script"
            echo ""
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --skip-apache    Skip automatic Apache configuration updates"
            echo "  --force, -f      Force update even if already on current version"
            echo "  --help, -h       Show this help message"
            echo ""
            echo "Examples:"
            echo "  $0                    # Normal update with Apache configuration"
            echo "  $0 --skip-apache      # Update without Apache configuration changes"
            echo "  $0 --force            # Force update even if versions match"
            echo "  $0 --force --skip-apache  # Force update without Apache changes"
            echo ""
            echo "When using --skip-apache:"
            echo "  - Apache configuration will not be modified"
            echo "  - The backend service will still be updated and restarted"
            echo "  - You must manually update your web server configuration if needed"
            echo ""
            echo "When using --force:"
            echo "  - Update will proceed even if current version matches new version"
            echo "  - Useful for reapplying fixes or refreshing files without version change"
            echo "  - All files will be updated regardless of version comparison"
            exit 0
            ;;
    esac
done

# Check if we're running as root
if [ "$EUID" -ne 0 ]; then
    print_error "This script must be run as root (use sudo)"
    exit 1
fi

# Function to get current version from installed system
get_current_version() {
    if [ -f "$APP_DIR/includes/common.inc" ]; then
        CURRENT_VERSION=$(grep -o 'V4\.[0-9]\+\.[0-9]\+' "$APP_DIR/includes/common.inc" | head -1)
        if [ -z "$CURRENT_VERSION" ]; then
            CURRENT_VERSION="unknown"
        fi
    else
        CURRENT_VERSION="not_installed"
    fi
    print_status "Current version: $CURRENT_VERSION"
}

# Function to get new version from update package
get_new_version() {
    if [ -f "$PROJECT_ROOT/includes/common.inc" ]; then
        NEW_VERSION=$(grep -o 'V4\.[0-9]\+\.[0-9]\+' "$PROJECT_ROOT/includes/common.inc" | head -1)
        VERSION_DATE=$(grep -o '"[^"]*"' "$PROJECT_ROOT/includes/common.inc" | grep -E '[A-Za-z]+ [0-9]+, [0-9]{4}' | head -1 | tr -d '"')
        if [ -z "$NEW_VERSION" ]; then
            NEW_VERSION="unknown"
        fi
    else
        print_error "Cannot determine new version. Make sure you're running from the update package directory."
        exit 1
    fi
    print_status "New version: $NEW_VERSION ($VERSION_DATE)"
}

# Function to compare versions
compare_versions() {
    if [ "$CURRENT_VERSION" = "not_installed" ]; then
        print_error "Supermon-NG is not currently installed. Please run install.sh instead."
        exit 1
    fi
    
    if [ "$CURRENT_VERSION" = "unknown" ] || [ "$NEW_VERSION" = "unknown" ]; then
        print_warning "Cannot determine version information. Proceeding with update..."
        return 0
    fi
    
    if [ "$CURRENT_VERSION" = "$NEW_VERSION" ]; then
        if [ "$FORCE_UPDATE" = true ]; then
            print_warning "Current version ($CURRENT_VERSION) matches new version ($NEW_VERSION), but --force flag is set."
            print_status "Proceeding with forced update..."
            return 0
        else
            print_warning "You are already running version $CURRENT_VERSION. No update needed."
            print_status "Use --force flag to update anyway (e.g., $0 --force)"
            exit 0
        fi
    fi
    
    print_status "Updating from $CURRENT_VERSION to $NEW_VERSION"
}

# Function to detect configuration changes
detect_config_changes() {
    print_status "Analyzing configuration changes..."
    
    CONFIG_CHANGED=false
    
    # Only check for new configuration variables in common.inc (core system changes)
    if [ -f "$APP_DIR/includes/common.inc" ] && [ -f "$PROJECT_ROOT/includes/common.inc" ]; then
        # Extract variable definitions (lines starting with $)
        current_vars=$(grep -E '^\$[A-Z_]+' "$APP_DIR/includes/common.inc" | sort)
        new_vars=$(grep -E '^\$[A-Z_]+' "$PROJECT_ROOT/includes/common.inc" | sort)
        
        if [ "$current_vars" != "$new_vars" ]; then
            print_warning "New configuration variables detected in common.inc"
            print_status "This may require updating global.inc with new options"
            CONFIG_CHANGED=true
        fi
    fi
    
    # Check for new template files that might be needed
    NEW_TEMPLATE_FILES=(
        "user_files/global.inc.example"
        "user_files/favini.inc"
    )
    
    for template_file in "${NEW_TEMPLATE_FILES[@]}"; do
        current_file="$APP_DIR/$template_file"
        new_file="$PROJECT_ROOT/$template_file"
        
        if [ ! -f "$current_file" ] && [ -f "$new_file" ]; then
            print_status "New template file available: $template_file"
            # Don't set CONFIG_CHANGED=true for new template files
        fi
    done
    
    # Note: We do NOT check allmon.ini, authusers.inc, authini.inc, favorites.ini, 
    # privatenodes.txt, or controlpanel.ini for changes because these are user-specific
    # configuration files that should ALWAYS be preserved regardless of template changes
    
    if [ "$CONFIG_CHANGED" = true ]; then
        print_warning "Configuration changes detected in core system files."
        print_status "Critical user files (allmon.ini, authusers.inc, etc.) will be preserved."
    else
        print_status "No significant configuration changes detected."
        print_status "All user configuration files will be preserved."
    fi
}

# Function to create backup
create_backup() {
    print_status "Creating backup of current installation..."
    
    BACKUP_DIR="/tmp/supermon-ng-backup-$(date +%Y%m%d_%H%M%S)"
    mkdir -p "$BACKUP_DIR"
    
    # Backup user_files if they exist
    if [ -d "$APP_DIR/user_files" ]; then
        print_status "Backing up user_files directory..."
        cp -r "$APP_DIR/user_files" "$BACKUP_DIR/"
        USER_FILES_BACKUP_DIR="$BACKUP_DIR/user_files"
    fi
    
    # Backup current version info
    echo "CURRENT_VERSION=$CURRENT_VERSION" > "$BACKUP_DIR/version_info"
    echo "BACKUP_DATE=$(date)" >> "$BACKUP_DIR/version_info"
    
    print_status "Backup created at: $BACKUP_DIR"
}

# Function to update application files
update_application() {
    print_status "Updating application files..."
    
    # Stop services
    print_status "Stopping services..."
    systemctl stop supermon-ng-backend 2>/dev/null || true
    systemctl stop supermon-ng-websocket 2>/dev/null || true
    systemctl stop supermon-ng-node-status.timer 2>/dev/null || true
    
    # Create temporary directory for new files
    TEMP_DIR="/tmp/supermon-ng-update-$(date +%Y%m%d_%H%M%S)"
    mkdir -p "$TEMP_DIR"
    
    # Copy only production files to temporary directory
    print_status "Preparing new files..."
    
    # Copy essential production files only (excludes development files like frontend/, README.md, install.sh, etc.)
    PRODUCTION_FILES=(
        "index.php"          # Main application entry point
        "composer.json"      # PHP dependencies
        "composer.lock"      # PHP dependency lock file
        "includes"           # PHP include files
        "src"                # PHP backend source code
        "vendor"             # PHP dependencies
        "public"             # Web-accessible files
        "cache"              # Application cache
        "logs"               # Log directory
        "config"             # Performance optimization configurations
    )
    
    for file in "${PRODUCTION_FILES[@]}"; do
        if [ -e "$PROJECT_ROOT/$file" ]; then
            print_status "Copying production file: $file"
            cp -r "$PROJECT_ROOT/$file" "$TEMP_DIR/"
        fi
    done
    
    # Copy scripts directory (excluding update.sh)
    if [ -d "$PROJECT_ROOT/scripts" ]; then
        mkdir -p "$TEMP_DIR/scripts"
        find "$PROJECT_ROOT/scripts" -name "*.php" -exec cp {} "$TEMP_DIR/scripts/" \;
        find "$PROJECT_ROOT/scripts" -name "*.sh" ! -name "update.sh" -exec cp {} "$TEMP_DIR/scripts/" \;
    fi
    
    # Always preserve critical user files, regardless of config changes
    if [ -d "$APP_DIR/user_files" ]; then
        print_status "Preserving critical user configuration files..."
        
        # Copy new user_files templates first
        if [ -d "$PROJECT_ROOT/user_files" ]; then
            cp -r "$PROJECT_ROOT/user_files" "$TEMP_DIR/"
        fi
        
        # List of critical files that should ALWAYS be preserved from user's existing installation
        CRITICAL_USER_FILES=(
            "allmon.ini"           # Node configuration - NEVER replace
            "authusers.inc"        # User authentication - NEVER replace
            "authini.inc"          # Authentication settings - NEVER replace
            "favorites.ini"        # User favorites - NEVER replace
            "privatenodes.txt"     # Private nodes list - NEVER replace
            "controlpanel.ini"     # Control panel settings - NEVER replace
            "global.inc"           # Global user configuration - NEVER replace
            ".htpasswd"            # Apache authentication file - NEVER replace
        )
        
        # List of user customization files that should ALWAYS be preserved
        USER_CUSTOMIZATION_FILES=(
            "header-background.jpg"
            "header-background.jpeg"
            "header-background.png"
            "header-background.gif"
            "header-background.webp"
        )
        
        # List of critical root-level files that should ALWAYS be preserved
        CRITICAL_ROOT_FILES=(
            "astdb.txt"            # Asterisk database - NEVER replace
        )
        
        # List of template files that should only be updated if they don't exist
        TEMPLATE_FILES=(
            "favini.inc"
            "global.inc.example"
        )
        
        # Always preserve these critical files from the existing installation
        for critical_file in "${CRITICAL_USER_FILES[@]}"; do
            if [ -f "$APP_DIR/user_files/$critical_file" ]; then
                print_status "Preserving critical file: $critical_file"
                cp "$APP_DIR/user_files/$critical_file" "$TEMP_DIR/user_files/"
            fi
        done
        
        # Always preserve user customization files from the existing installation
        for custom_file in "${USER_CUSTOMIZATION_FILES[@]}"; do
            if [ -f "$APP_DIR/user_files/$custom_file" ]; then
                print_status "Preserving user customization file: $custom_file"
                cp "$APP_DIR/user_files/$custom_file" "$TEMP_DIR/user_files/"
            fi
        done
        
        # Protect ALL .inc and .ini files in user_files/ that users may have added
        print_status "Protecting all user configuration files (.inc and .ini)..."
        find "$APP_DIR/user_files" -maxdepth 1 -name "*.inc" -o -name "*.ini" | while read -r config_file; do
            filename=$(basename "$config_file")
            # Skip files that are already handled by critical files or template files
            if [[ ! " ${CRITICAL_USER_FILES[@]} " =~ " ${filename} " ]] && [[ ! " ${TEMPLATE_FILES[@]} " =~ " ${filename} " ]]; then
                print_status "Preserving user configuration file: $filename"
                cp "$config_file" "$TEMP_DIR/user_files/"
            fi
        done
        
        # Always preserve critical root-level files from the existing installation
        for critical_file in "${CRITICAL_ROOT_FILES[@]}"; do
            if [ -f "$APP_DIR/$critical_file" ]; then
                print_status "Preserving critical root file: $critical_file"
                cp "$APP_DIR/$critical_file" "$TEMP_DIR/"
            fi
        done
        
        # Handle astdb.txt template - preserve user's existing file or copy template if none exists
        if [ -f "$APP_DIR/astdb.txt" ]; then
            print_status "Preserving existing astdb.txt file"
            cp "$APP_DIR/astdb.txt" "$TEMP_DIR/"
        elif [ -f "$PROJECT_ROOT/astdb.txt" ]; then
            print_status "Copying astdb.txt template (no existing file found)"
            cp "$PROJECT_ROOT/astdb.txt" "$TEMP_DIR/"
        fi
        
        # Preserve sbin directory (contains user scripts and configurations)
        if [ -d "$APP_DIR/user_files/sbin" ]; then
            print_status "Preserving sbin directory..."
            cp -r "$APP_DIR/user_files/sbin" "$TEMP_DIR/user_files/"
        fi
        
        # Preserve preferences directory
        if [ -d "$APP_DIR/user_files/preferences" ]; then
            print_status "Preserving preferences directory..."
            cp -r "$APP_DIR/user_files/preferences" "$TEMP_DIR/user_files/"
        fi
        
        # Note: global.inc is now protected as a critical file and will be preserved automatically
        
        # Handle other template files (favini.inc, etc.) - only update if they don't exist
        
        for template_file in "${TEMPLATE_FILES[@]}"; do
            if [ ! -f "$APP_DIR/user_files/$template_file" ] && [ -f "$PROJECT_ROOT/user_files/$template_file" ]; then
                print_status "Adding new template file: $template_file"
                cp "$PROJECT_ROOT/user_files/$template_file" "$TEMP_DIR/user_files/"
            elif [ -f "$APP_DIR/user_files/$template_file" ]; then
                print_status "Preserving existing template file: $template_file"
                cp "$APP_DIR/user_files/$template_file" "$TEMP_DIR/user_files/"
            fi
        done
        
        print_status "User configuration files preservation completed"
    fi
    
    # Replace application directory
    print_status "Installing new version..."
    rm -rf "$APP_DIR"
    mv "$TEMP_DIR" "$APP_DIR"
    
    # Create necessary directories if they don't exist
    print_status "Creating necessary directories..."
    mkdir -p "$APP_DIR/logs"
    mkdir -p "$APP_DIR/database"
    mkdir -p "$APP_DIR/cache"
    
    # Set proper permissions
    chown -R www-data:www-data "$APP_DIR"
    chmod -R 755 "$APP_DIR"
    chmod -R 755 "$APP_DIR/logs" 2>/dev/null || true
    chmod -R 755 "$APP_DIR/database" 2>/dev/null || true
    chmod -R 755 "$APP_DIR/cache" 2>/dev/null || true
    chmod -R 755 "$APP_DIR/user_files" 2>/dev/null || true
    
    # Set permissions for config directory
    chmod -R 644 "$APP_DIR/config" 2>/dev/null || true
}

# Function to update system services
update_services() {
    print_status "Updating system services..."
    
    # Update backend service
    cat > "/etc/systemd/system/supermon-ng-backend.service" << EOF
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
    
    # Update database auto-update service
    cat > "/etc/systemd/system/supermon-ng-database-update.service" << EOF
[Unit]
Description=Supermon-NG Database Auto-Update Service
After=network.target

[Service]
Type=oneshot
User=root
WorkingDirectory=$APP_DIR
ExecStart=/usr/bin/php $APP_DIR/scripts/database-auto-update.php

[Install]
WantedBy=multi-user.target
EOF
    
    # Update database auto-update timer
    cat > "/etc/systemd/system/supermon-ng-database-update.timer" << EOF
[Unit]
Description=Run Supermon-NG Database Update every 3 hours
Requires=supermon-ng-database-update.service

[Timer]
OnBootSec=5min
OnUnitActiveSec=3h
AccuracySec=5min

[Install]
WantedBy=timers.target
EOF
    
    # Update node status service
    cat > "/etc/systemd/system/supermon-ng-node-status.service" << EOF
[Unit]
Description=Supermon-NG Node Status Update Service
After=network.target

[Service]
Type=oneshot
User=root
WorkingDirectory=$APP_DIR/user_files/sbin
ExecStart=/usr/bin/python3 $APP_DIR/user_files/sbin/ast_node_status_update.py

[Install]
WantedBy=multi-user.target
EOF
    
    # Update node status timer
    cat > "/etc/systemd/system/supermon-ng-node-status.timer" << EOF
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
    
    # Update WebSocket service
    if [ -f "$APP_DIR/systemd/supermon-ng-websocket.service" ]; then
        cp "$APP_DIR/systemd/supermon-ng-websocket.service" /etc/systemd/system/
        print_status "WebSocket service file updated"
    fi
    
    # Reload systemd and restart services
    systemctl daemon-reload
    systemctl enable supermon-ng-backend
    systemctl restart supermon-ng-backend
    
    if [ -f "/etc/systemd/system/supermon-ng-websocket.service" ]; then
        systemctl enable supermon-ng-websocket
        systemctl restart supermon-ng-websocket
        print_status "WebSocket service restarted"
    fi
    
    # Enable and start node status timer
    systemctl enable supermon-ng-node-status.timer
    systemctl restart supermon-ng-node-status.timer
    
    # Enable and start database auto-update timer
    systemctl enable supermon-ng-database-update.timer
    systemctl restart supermon-ng-database-update.timer
    
    print_status "All services updated, enabled, and started"
}

# Function to update sudoers configuration
update_sudoers() {
    print_status "Updating sudoers configuration..."
    
    SUDOERS_FILE="/etc/sudoers.d/011_www-nopasswd"
    SUDOERS_SOURCE="$PROJECT_ROOT/sudoers.d/011_www-nopasswd"
    
    if [ -f "$SUDOERS_SOURCE" ]; then
        # Backup existing sudoers file
        if [ -f "$SUDOERS_FILE" ]; then
            cp "$SUDOERS_FILE" "$SUDOERS_FILE.backup"
        fi
        
        # Copy new sudoers file
        cp "$SUDOERS_SOURCE" "$SUDOERS_FILE"
        chmod 0440 "$SUDOERS_FILE"
        chown root:root "$SUDOERS_FILE"
        
        # Validate sudoers syntax
        if visudo -c -f "$SUDOERS_FILE"; then
            print_status "Sudoers configuration updated successfully"
        else
            print_warning "Invalid sudoers syntax, restoring backup"
            if [ -f "$SUDOERS_FILE.backup" ]; then
                mv "$SUDOERS_FILE.backup" "$SUDOERS_FILE"
            else
                rm -f "$SUDOERS_FILE"
            fi
        fi
    else
        print_warning "Sudoers source file not found at $SUDOERS_SOURCE"
    fi
}

# Function to update PHP dependencies
update_dependencies() {
    print_status "Updating PHP dependencies..."
    cd "$APP_DIR"
    sudo -u www-data composer install --no-dev --optimize-autoloader
}

# Function to update frontend
update_frontend() {
    print_status "Updating frontend..."
    
    # Check if we're in a development environment with frontend source
    if [ -d "$PROJECT_ROOT/frontend" ] && [ -f "$PROJECT_ROOT/frontend/package.json" ]; then
        # Development mode - build from source
        print_status "Building frontend from source..."
        cd "$PROJECT_ROOT/frontend"
        npm install
        npm run build
        cp -r dist/* "$APP_DIR/public/"
    elif [ -d "$PROJECT_ROOT/frontend/dist" ] && [ -f "$PROJECT_ROOT/frontend/dist/index.html" ]; then
        # Production mode - pre-built frontend in project root
        print_status "Using pre-built frontend from project root..."
        cp -r "$PROJECT_ROOT/frontend/dist"/* "$APP_DIR/public/"
    else
        # Frontend files are already in public directory from production files copy
        print_status "Frontend files already updated via production files copy..."
    fi
}

# Function to update Apache configuration
update_apache_config() {
    if [ "$SKIP_APACHE" = true ]; then
        print_status "Skipping Apache configuration update (--skip-apache flag)"
        return 0
    fi
    
    print_status "Updating Apache configuration..."
    
    # Check if Apache config needs updating
    APACHE_TEMPLATE="$APP_DIR/apache-config-template.conf"
    APACHE_SITE_FILE="/etc/apache2/sites-available/supermon-ng.conf"
    
    if [ -f "$APACHE_TEMPLATE" ]; then
        # Detect IP addresses for new config
        if [ -f "$APP_DIR/scripts/update.sh" ]; then
            # Use the IP detection function from install.sh
            source "$APP_DIR/scripts/update.sh" 2>/dev/null || true
        fi
        
        # Update Apache site configuration
        # Note: No backup of system files - Apache config is managed by installation
        cp "$APACHE_TEMPLATE" "$APACHE_SITE_FILE"
        
        # Disable the default site to avoid conflicts
        print_status "Disabling default Apache site..."
        a2dissite -q 000-default 2>/dev/null || {
            print_warning "Failed to disable default site (may not exist)"
        }
        
        # Enable the supermon-ng site
        print_status "Enabling supermon-ng Apache site..."
        a2ensite -q supermon-ng 2>/dev/null || {
            print_warning "Failed to enable Apache site automatically"
        }
        
        # Test and restart Apache
        if apache2ctl configtest >/dev/null 2>&1; then
            systemctl restart apache2
            print_status "Apache configuration updated successfully"
        else
            print_warning "Apache configuration test failed. Please check manually."
        fi
    fi
}

# Function to run post-update tasks
post_update_tasks() {
    print_status "Running post-update tasks..."
    
    # Clear any caches (including ASTDB cache)
    if [ -d "$APP_DIR/cache" ]; then
        print_status "Clearing application caches..."
        rm -rf "$APP_DIR/cache"/*
        print_status "ASTDB cache cleared - will be regenerated on next access"
    fi
    
    # Update file permissions
    chown -R www-data:www-data "$APP_DIR"
    chmod +x "$APP_DIR/scripts"/*.sh 2>/dev/null || true
    chmod +x "$APP_DIR/user_files/set_password.sh" 2>/dev/null || true
    chmod +x "$APP_DIR/scripts/manage_users.php" 2>/dev/null || true
    chmod +x "$APP_DIR/scripts/database-auto-update.php" 2>/dev/null || true
}

# Function to display update summary
display_summary() {
    echo ""
    echo -e "${GREEN}🎉 Update Complete!${NC}"
    echo "=================="
    echo ""
    echo "📊 Update Summary:"
    echo "   ✅ Updated from $CURRENT_VERSION to $NEW_VERSION"
    echo "   ✅ Application files updated"
    echo "   ✅ System services updated"
    echo "   ✅ Dependencies updated"
    echo "   ✅ Frontend updated"
    echo "   ✅ Performance optimizations included"
    
    if [ "$CONFIG_CHANGED" = true ]; then
        echo "   ⚠️  Configuration changes detected"
        echo "   📁 User files backed up to: $USER_FILES_BACKUP_DIR"
        echo ""
        echo "⚠️  IMPORTANT: Configuration changes detected!"
        echo "   Please review your configuration files in $APP_DIR/user_files/"
        echo "   Compare with the backup in $USER_FILES_BACKUP_DIR"
        echo "   Update any new configuration options as needed."
    else
        echo "   ✅ User configurations preserved (no changes detected)"
    fi
    
    echo ""
    echo "🌐 Access your updated Supermon-NG application at:"
    echo "   - http://localhost"
    if [ -n "$IP_ADDRESSES" ]; then
        for ip in "${IP_ADDRESSES[@]}"; do
            echo "   - http://$ip"
        done
    fi
    
    echo ""
    echo "🔧 Service Status:"
    systemctl is-active supermon-ng-backend > /dev/null && echo "   ✅ Backend: Running" || echo "   ❌ Backend: Failed"
    systemctl is-active supermon-ng-websocket > /dev/null && echo "   ✅ WebSocket: Running" || echo "   ❌ WebSocket: Failed"
    systemctl is-active apache2 > /dev/null && echo "   ✅ Apache: Running" || echo "   ❌ Apache: Failed"
    
    echo ""
    echo "⏰ Scheduled Tasks:"
    systemctl is-active supermon-ng-node-status.timer > /dev/null 2>&1 && echo "   ✅ Node Status Updates: Every 3 minutes" || echo "   ⚠️  Node Status Updates: Not configured"
    systemctl is-active supermon-ng-database-update.timer > /dev/null 2>&1 && echo "   ✅ Database Updates: Every 3 hours" || echo "   ⚠️  Database Updates: Not configured"
    
    echo ""
    echo "📝 Next Steps:"
    if [ "$CONFIG_CHANGED" = true ]; then
        echo "   1. Review configuration changes in user_files/"
        echo "   2. Update any new configuration options"
        echo "   3. Test the web interface"
        echo "   4. Verify all functionality works as expected"
    else
        echo "   1. Test the web interface"
        echo "   2. Verify all functionality works as expected"
    fi
    echo "   3. Check logs if you encounter any issues"
    echo ""
    echo "🚀 Performance Optimizations Available:"
    echo "   • PHP OPcache configuration: $APP_DIR/config/php-opcache.ini"
    echo "   • Apache performance config: $APP_DIR/config/apache-performance.conf"
    echo "   • ASTDB cache system: Multi-level caching with 84.8% compression"
    echo "   • Frontend optimizations: Browser-side caching and batch operations"
    echo "   • Database optimization: Query caching with Doctrine DBAL"
    echo "   • Performance monitoring: Run scripts/performance-report.sh for metrics"
    echo "   • See PERFORMANCE_OPTIMIZATIONS.md for setup instructions"
    echo ""
}

# Main update process
main() {
    get_current_version
    get_new_version
    compare_versions
    detect_config_changes
    create_backup
    update_application
    update_services
    update_sudoers
    update_dependencies
    update_frontend
    update_apache_config
    post_update_tasks
    display_summary
}

# Run main function
main "$@"
