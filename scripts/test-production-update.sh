#!/bin/bash

# Supermon-ng Production Test Update Script
# This script allows testing updates in production without version bumps

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
APP_DIR="/var/www/html/supermon-ng"
PROJECT_ROOT="/var/www/html/supermon-ng"
TARBALL_PATH=""
BACKUP_DIR="/tmp/supermon-ng-production-backup-$(date +%Y%m%d_%H%M%S)"
FORCE_UPDATE=false

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to show usage
show_usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  -t, --tarball PATH    Path to the tarball to test"
    echo "  -f, --force           Force update even if versions match"
    echo "  -h, --help            Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 -t /tmp/supermon-ng-V4.0.4.tar.xz"
    echo "  $0 -t /tmp/supermon-ng-V4.0.4.tar.xz -f"
    echo ""
    echo "This script will:"
    echo "  1. Create a backup of your current installation"
    echo "  2. Extract and apply the new tarball"
    echo "  3. Preserve all user configuration files"
    echo "  4. Allow you to test the update in production"
    echo "  5. Provide easy restoration if needed"
    echo ""
    echo "WARNING: This modifies your production installation!"
    echo "A backup will be created automatically."
}

# Function to check if running as root
check_root() {
    if [ "$EUID" -eq 0 ]; then
        print_error "This script should not be run as root for safety reasons"
        exit 1
    fi
}

# Function to check if production directory exists
check_production() {
    if [ ! -d "$APP_DIR" ]; then
        print_error "Production directory $APP_DIR does not exist"
        exit 1
    fi
}

# Function to create backup
create_backup() {
    print_status "Creating backup of current installation..."
    
    if [ -d "$BACKUP_DIR" ]; then
        print_warning "Backup directory already exists, removing..."
        rm -rf "$BACKUP_DIR"
    fi
    
    mkdir -p "$BACKUP_DIR"
    
    # Copy the entire production directory
    cp -r "$APP_DIR" "$BACKUP_DIR/"
    
    print_success "Backup created at: $BACKUP_DIR"
}

# Function to extract tarball
extract_tarball() {
    if [ -z "$TARBALL_PATH" ]; then
        print_error "No tarball specified. Use -t option."
        exit 1
    fi
    
    if [ ! -f "$TARBALL_PATH" ]; then
        print_error "Tarball not found: $TARBALL_PATH"
        exit 1
    fi
    
    print_status "Extracting tarball: $TARBALL_PATH"
    
    # Create temporary extraction directory
    TEMP_EXTRACT="/tmp/supermon-ng-extract-$(date +%Y%m%d_%H%M%S)"
    mkdir -p "$TEMP_EXTRACT"
    
    # Extract tarball
    tar -xJf "$TARBALL_PATH" -C "$TEMP_EXTRACT"
    
    # Copy extracted files to production directory
    print_status "Installing tarball to production directory..."
    sudo cp -r "$TEMP_EXTRACT"/* "$APP_DIR/"
    
    # Set proper ownership
    sudo chown -R www-data:www-data "$APP_DIR"
    
    # Clean up temporary directory
    rm -rf "$TEMP_EXTRACT"
    
    print_success "Tarball installed to production directory"
}

# Function to preserve user files
preserve_user_files() {
    print_status "Preserving user configuration files..."
    
    # List of critical files that should ALWAYS be preserved
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
    
    # Always preserve these critical files from the backup
    for critical_file in "${CRITICAL_USER_FILES[@]}"; do
        if [ -f "$BACKUP_DIR/supermon-ng/user_files/$critical_file" ]; then
            print_status "Restoring critical file: $critical_file"
            sudo cp "$BACKUP_DIR/supermon-ng/user_files/$critical_file" "$APP_DIR/user_files/"
        fi
    done
    
    # Always preserve user customization files from the backup
    for custom_file in "${USER_CUSTOMIZATION_FILES[@]}"; do
        if [ -f "$BACKUP_DIR/supermon-ng/user_files/$custom_file" ]; then
            print_status "Restoring user customization file: $custom_file"
            sudo cp "$BACKUP_DIR/supermon-ng/user_files/$custom_file" "$APP_DIR/user_files/"
        fi
    done
    
    # Always preserve critical root-level files from the backup
    for critical_file in "${CRITICAL_ROOT_FILES[@]}"; do
        if [ -f "$BACKUP_DIR/supermon-ng/$critical_file" ]; then
            print_status "Restoring critical root file: $critical_file"
            sudo cp "$BACKUP_DIR/supermon-ng/$critical_file" "$APP_DIR/"
        fi
    done
    
    # Protect ALL .inc and .ini files in user_files/ that users may have added
    print_status "Protecting all user configuration files (.inc and .ini)..."
    find "$BACKUP_DIR/supermon-ng/user_files" -maxdepth 1 -name "*.inc" -o -name "*.ini" | while read -r config_file; do
        filename=$(basename "$config_file")
        # Skip files that are already handled by critical files
        if [[ ! " ${CRITICAL_USER_FILES[@]} " =~ " ${filename} " ]]; then
            print_status "Restoring user configuration file: $filename"
            sudo cp "$config_file" "$APP_DIR/user_files/"
        fi
    done
    
    # Preserve sbin directory
    if [ -d "$BACKUP_DIR/supermon-ng/user_files/sbin" ]; then
        print_status "Preserving sbin directory..."
        sudo cp -r "$BACKUP_DIR/supermon-ng/user_files/sbin" "$APP_DIR/user_files/"
    fi
    
    # Preserve preferences directory
    if [ -d "$BACKUP_DIR/supermon-ng/user_files/preferences" ]; then
        print_status "Preserving preferences directory..."
        sudo cp -r "$BACKUP_DIR/supermon-ng/user_files/preferences" "$APP_DIR/user_files/"
    fi
    
    # Set proper ownership
    sudo chown -R www-data:www-data "$APP_DIR"
    
    print_success "User files preserved"
}

# Function to create necessary directories
create_directories() {
    print_status "Creating necessary directories..."
    sudo mkdir -p "$APP_DIR/logs"
    sudo mkdir -p "$APP_DIR/database"
    sudo mkdir -p "$APP_DIR/cache"
    sudo chown -R www-data:www-data "$APP_DIR/logs" "$APP_DIR/database" "$APP_DIR/cache"
}

# Function to restart services
restart_services() {
    print_status "Restarting services..."
    
    # Restart Apache
    sudo systemctl reload apache2
    
    # Restart any Supermon-ng services if they exist
    if systemctl is-active --quiet supermon-ng-node-status.service 2>/dev/null; then
        sudo systemctl restart supermon-ng-node-status.service
    fi
    
    print_success "Services restarted"
}

# Function to show testing instructions
show_testing_instructions() {
    echo ""
    print_success "Production update complete!"
    echo ""
    echo "Testing Instructions:"
    echo "===================="
    echo ""
    echo "1. Test your application at: http://sm.w5gle.us"
    echo "2. Check all functionality (login, favorites, node control, etc.)"
    echo "3. Verify your user configuration is preserved"
    echo "4. Check logs if needed:"
    echo "   - Apache: sudo tail -f /var/log/apache2/sm.w5gle.us_error.log"
    echo "   - Application: $APP_DIR/logs/"
    echo ""
    echo "If issues are found:"
    echo "  - Restore from backup: $0 --restore"
    echo ""
    echo "Backup location: $BACKUP_DIR"
    echo "Production directory: $APP_DIR"
}

# Function to restore from backup
restore_from_backup() {
    print_status "Restoring from backup..."
    
    if [ ! -d "$BACKUP_DIR" ]; then
        print_error "No backup found. Please specify backup directory."
        exit 1
    fi
    
    # Remove current production directory
    sudo rm -rf "$APP_DIR"
    
    # Restore from backup
    sudo cp -r "$BACKUP_DIR/supermon-ng" "$APP_DIR"
    
    # Set proper ownership
    sudo chown -R www-data:www-data "$APP_DIR"
    
    # Restart services
    restart_services
    
    print_success "Restoration complete!"
}

# Parse command line arguments
RESTORE=false

while [[ $# -gt 0 ]]; do
    case $1 in
        -t|--tarball)
            TARBALL_PATH="$2"
            shift 2
            ;;
        -f|--force)
            FORCE_UPDATE=true
            shift
            ;;
        --restore)
            RESTORE=true
            shift
            ;;
        -h|--help)
            show_usage
            exit 0
            ;;
        *)
            print_error "Unknown option: $1"
            show_usage
            exit 1
            ;;
    esac
done

# Main execution
main() {
    print_status "Supermon-ng Production Test Update Script"
    print_status "=========================================="
    
    # Check if we're restoring
    if [ "$RESTORE" = true ]; then
        restore_from_backup
        exit 0
    fi
    
    # Check prerequisites
    check_root
    check_production
    
    # Create backup
    create_backup
    
    # Extract tarball
    extract_tarball
    
    # Preserve user files
    preserve_user_files
    
    # Create necessary directories
    create_directories
    
    # Restart services
    restart_services
    
    # Show testing instructions
    show_testing_instructions
}

# Run main function
main "$@"
