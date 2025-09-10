#!/bin/bash

# Supermon-ng Component Test Script
# This script allows testing specific components without full updates

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
APP_DIR="/var/www/html/supermon-ng"
TARBALL_PATH=""
COMPONENT=""

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
    echo "  -c, --component NAME  Component to test (frontend, backend, scripts)"
    echo "  -h, --help            Show this help message"
    echo ""
    echo "Components:"
    echo "  frontend              Test frontend files only"
    echo "  backend               Test backend files only"
    echo "  scripts               Test scripts only"
    echo "  all                   Test all components (default)"
    echo ""
    echo "Examples:"
    echo "  $0 -t /tmp/supermon-ng-V4.0.4.tar.xz -c frontend"
    echo "  $0 -t /tmp/supermon-ng-V4.0.4.tar.xz -c backend"
    echo "  $0 -t /tmp/supermon-ng-V4.0.4.tar.xz -c scripts"
    echo ""
    echo "This script will:"
    echo "  1. Create a backup of the specified component"
    echo "  2. Extract and test the component from tarball"
    echo "  3. Allow you to test the component"
    echo "  4. Provide easy restoration if needed"
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

# Function to create component backup
create_component_backup() {
    local component="$1"
    local backup_dir="/tmp/supermon-ng-component-backup-$(date +%Y%m%d_%H%M%S)"
    
    print_status "Creating backup of $component component..."
    
    mkdir -p "$backup_dir"
    
    case "$component" in
        "frontend")
            if [ -d "$APP_DIR/public" ]; then
                cp -r "$APP_DIR/public" "$backup_dir/"
            fi
            if [ -d "$APP_DIR/frontend" ]; then
                cp -r "$APP_DIR/frontend" "$backup_dir/"
            fi
            ;;
        "backend")
            if [ -d "$APP_DIR/src" ]; then
                cp -r "$APP_DIR/src" "$backup_dir/"
            fi
            if [ -d "$APP_DIR/includes" ]; then
                cp -r "$APP_DIR/includes" "$backup_dir/"
            fi
            if [ -d "$APP_DIR/vendor" ]; then
                cp -r "$APP_DIR/vendor" "$backup_dir/"
            fi
            if [ -f "$APP_DIR/composer.json" ]; then
                cp "$APP_DIR/composer.json" "$backup_dir/"
            fi
            if [ -f "$APP_DIR/composer.lock" ]; then
                cp "$APP_DIR/composer.lock" "$backup_dir/"
            fi
            ;;
        "scripts")
            if [ -d "$APP_DIR/scripts" ]; then
                cp -r "$APP_DIR/scripts" "$backup_dir/"
            fi
            ;;
        "all")
            cp -r "$APP_DIR" "$backup_dir/"
            ;;
    esac
    
    echo "$backup_dir"
}

# Function to extract component from tarball
extract_component() {
    local component="$1"
    local tarball="$2"
    
    print_status "Extracting $component component from tarball..."
    
    # Create temporary extraction directory
    local temp_extract="/tmp/supermon-ng-extract-$(date +%Y%m%d_%H%M%S)"
    mkdir -p "$temp_extract"
    
    # Extract tarball
    tar -xJf "$tarball" -C "$temp_extract"
    
    # Copy component files
    case "$component" in
        "frontend")
            if [ -d "$temp_extract/public" ]; then
                sudo cp -r "$temp_extract/public"/* "$APP_DIR/public/"
            fi
            if [ -d "$temp_extract/frontend" ]; then
                sudo cp -r "$temp_extract/frontend"/* "$APP_DIR/frontend/"
            fi
            ;;
        "backend")
            if [ -d "$temp_extract/src" ]; then
                sudo cp -r "$temp_extract/src"/* "$APP_DIR/src/"
            fi
            if [ -d "$temp_extract/includes" ]; then
                sudo cp -r "$temp_extract/includes"/* "$APP_DIR/includes/"
            fi
            if [ -d "$temp_extract/vendor" ]; then
                sudo cp -r "$temp_extract/vendor"/* "$APP_DIR/vendor/"
            fi
            if [ -f "$temp_extract/composer.json" ]; then
                sudo cp "$temp_extract/composer.json" "$APP_DIR/"
            fi
            if [ -f "$temp_extract/composer.lock" ]; then
                sudo cp "$temp_extract/composer.lock" "$APP_DIR/"
            fi
            ;;
        "scripts")
            if [ -d "$temp_extract/scripts" ]; then
                sudo cp -r "$temp_extract/scripts"/* "$APP_DIR/scripts/"
            fi
            ;;
        "all")
            sudo cp -r "$temp_extract"/* "$APP_DIR/"
            ;;
    esac
    
    # Set proper ownership
    sudo chown -R www-data:www-data "$APP_DIR"
    
    # Clean up temporary directory
    rm -rf "$temp_extract"
    
    print_success "$component component extracted and installed"
}

# Function to restart services
restart_services() {
    print_status "Restarting services..."
    
    # Restart Apache
    sudo systemctl reload apache2
    
    print_success "Services restarted"
}

# Function to show testing instructions
show_testing_instructions() {
    local component="$1"
    local backup_dir="$2"
    
    echo ""
    print_success "$component component update complete!"
    echo ""
    echo "Testing Instructions:"
    echo "===================="
    echo ""
    echo "1. Test your application at: http://sm.w5gle.us"
    echo "2. Check the $component functionality"
    echo "3. Verify everything works as expected"
    echo ""
    echo "If issues are found:"
    echo "  - Restore from backup: $0 --restore $backup_dir"
    echo ""
    echo "Backup location: $backup_dir"
    echo "Production directory: $APP_DIR"
}

# Function to restore from backup
restore_from_backup() {
    local backup_dir="$1"
    
    if [ -z "$backup_dir" ]; then
        print_error "No backup directory specified"
        exit 1
    fi
    
    if [ ! -d "$backup_dir" ]; then
        print_error "Backup directory not found: $backup_dir"
        exit 1
    fi
    
    print_status "Restoring from backup: $backup_dir"
    
    # Restore files
    sudo cp -r "$backup_dir"/* "$APP_DIR/"
    
    # Set proper ownership
    sudo chown -R www-data:www-data "$APP_DIR"
    
    # Restart services
    restart_services
    
    print_success "Restoration complete!"
}

# Parse command line arguments
RESTORE=false
BACKUP_DIR=""

while [[ $# -gt 0 ]]; do
    case $1 in
        -t|--tarball)
            TARBALL_PATH="$2"
            shift 2
            ;;
        -c|--component)
            COMPONENT="$2"
            shift 2
            ;;
        --restore)
            RESTORE=true
            BACKUP_DIR="$2"
            shift 2
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
    print_status "Supermon-ng Component Test Script"
    print_status "================================="
    
    # Check if we're restoring
    if [ "$RESTORE" = true ]; then
        restore_from_backup "$BACKUP_DIR"
        exit 0
    fi
    
    # Set default component
    if [ -z "$COMPONENT" ]; then
        COMPONENT="all"
    fi
    
    # Validate component
    case "$COMPONENT" in
        "frontend"|"backend"|"scripts"|"all")
            ;;
        *)
            print_error "Invalid component: $COMPONENT"
            show_usage
            exit 1
            ;;
    esac
    
    # Check prerequisites
    check_root
    check_production
    
    if [ -z "$TARBALL_PATH" ]; then
        print_error "No tarball specified. Use -t option."
        show_usage
        exit 1
    fi
    
    if [ ! -f "$TARBALL_PATH" ]; then
        print_error "Tarball not found: $TARBALL_PATH"
        exit 1
    fi
    
    # Create backup
    local backup_dir
    backup_dir=$(create_component_backup "$COMPONENT")
    
    # Extract component
    extract_component "$COMPONENT" "$TARBALL_PATH"
    
    # Restart services
    restart_services
    
    # Show testing instructions
    show_testing_instructions "$COMPONENT" "$backup_dir"
}

# Run main function
main "$@"
