#!/bin/bash

# Supermon-ng Test Update Script
# This script allows testing updates without affecting your production installation

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PRODUCTION_DIR="/var/www/html/supermon-ng"
TEST_DIR="/var/www/html/supermon-ng-test"
BACKUP_DIR="/tmp/supermon-ng-backup-$(date +%Y%m%d_%H%M%S)"
TARBALL_PATH=""

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
    echo "  -c, --clean           Clean up test environment before starting"
    echo "  -k, --keep            Keep test environment after testing"
    echo "  -h, --help            Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 -t /tmp/supermon-ng-V4.0.4.tar.xz"
    echo "  $0 -t /tmp/supermon-ng-V4.0.4.tar.xz -c"
    echo "  $0 -t /tmp/supermon-ng-V4.0.4.tar.xz -k"
    echo ""
    echo "This script will:"
    echo "  1. Create a backup of your current installation"
    echo "  2. Set up a test environment"
    echo "  3. Extract and test the new tarball"
    echo "  4. Allow you to test the update"
    echo "  5. Optionally restore your original installation"
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
    if [ ! -d "$PRODUCTION_DIR" ]; then
        print_error "Production directory $PRODUCTION_DIR does not exist"
        exit 1
    fi
}

# Function to backup current installation
backup_production() {
    print_status "Creating backup of current installation..."
    
    if [ -d "$BACKUP_DIR" ]; then
        print_warning "Backup directory already exists, removing..."
        rm -rf "$BACKUP_DIR"
    fi
    
    mkdir -p "$BACKUP_DIR"
    
    # Copy the entire production directory
    cp -r "$PRODUCTION_DIR" "$BACKUP_DIR/"
    
    print_success "Backup created at: $BACKUP_DIR"
}

# Function to clean test environment
clean_test_env() {
    print_status "Cleaning test environment..."
    
    if [ -d "$TEST_DIR" ]; then
        print_warning "Removing existing test directory..."
        sudo rm -rf "$TEST_DIR"
    fi
}

# Function to setup test environment
setup_test_env() {
    print_status "Setting up test environment..."
    
    # Create test directory
    sudo mkdir -p "$TEST_DIR"
    
    # Copy production files to test directory
    print_status "Copying production files to test directory..."
    sudo cp -r "$PRODUCTION_DIR"/* "$TEST_DIR/"
    
    # Set proper ownership
    sudo chown -R www-data:www-data "$TEST_DIR"
    
    print_success "Test environment created at: $TEST_DIR"
}

# Function to extract and test tarball
test_tarball() {
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
    
    # Copy extracted files to test directory
    print_status "Installing tarball to test directory..."
    sudo cp -r "$TEMP_EXTRACT"/* "$TEST_DIR/"
    
    # Set proper ownership
    sudo chown -R www-data:www-data "$TEST_DIR"
    
    # Clean up temporary directory
    rm -rf "$TEMP_EXTRACT"
    
    print_success "Tarball installed to test directory"
}

# Function to create test Apache configuration
create_test_apache_config() {
    print_status "Creating test Apache configuration..."
    
    # Create test Apache site configuration
    cat > "/tmp/supermon-ng-test.conf" << EOF
<VirtualHost *:80>
    ServerName sm-test.w5gle.us
    ServerAdmin webmaster@localhost
    DocumentRoot $TEST_DIR/public

    # Proxy API requests to backend
    ProxyPreserveHost On
    ProxyPass /api http://localhost:8000/api
    ProxyPassReverse /api http://localhost:8000/api

    # Serve static files from public directory
    <Directory "$TEST_DIR/public">
        AllowOverride None
        Require all granted
        RewriteEngine On
        RewriteBase /
        RewriteRule ^index\.html$ - [L]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule . /index.html [L]
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/sm-test_error.log
    CustomLog \${APACHE_LOG_DIR}/sm-test_access.log combined
</VirtualHost>
EOF

    # Copy to Apache sites-available
    sudo cp "/tmp/supermon-ng-test.conf" "/etc/apache2/sites-available/supermon-ng-test.conf"
    
    # Enable the site
    sudo a2ensite supermon-ng-test.conf
    
    # Test Apache configuration
    sudo apache2ctl configtest
    
    # Reload Apache
    sudo systemctl reload apache2
    
    # Clean up temporary file
    rm "/tmp/supermon-ng-test.conf"
    
    print_success "Test Apache configuration created and enabled"
    print_status "Test site available at: http://sm-test.w5gle.us"
}

# Function to restore production
restore_production() {
    print_status "Restoring production installation..."
    
    if [ ! -d "$BACKUP_DIR" ]; then
        print_error "Backup directory not found: $BACKUP_DIR"
        exit 1
    fi
    
    # Remove current production directory
    sudo rm -rf "$PRODUCTION_DIR"
    
    # Restore from backup
    sudo cp -r "$BACKUP_DIR/supermon-ng" "$PRODUCTION_DIR"
    
    # Set proper ownership
    sudo chown -R www-data:www-data "$PRODUCTION_DIR"
    
    print_success "Production installation restored"
}

# Function to cleanup test environment
cleanup_test() {
    print_status "Cleaning up test environment..."
    
    # Disable test Apache site
    sudo a2dissite supermon-ng-test.conf 2>/dev/null || true
    
    # Remove test Apache configuration
    sudo rm -f "/etc/apache2/sites-available/supermon-ng-test.conf"
    
    # Reload Apache
    sudo systemctl reload apache2
    
    # Remove test directory
    if [ -d "$TEST_DIR" ]; then
        sudo rm -rf "$TEST_DIR"
    fi
    
    print_success "Test environment cleaned up"
}

# Function to show testing instructions
show_testing_instructions() {
    echo ""
    print_success "Test environment is ready!"
    echo ""
    echo "Testing Instructions:"
    echo "===================="
    echo ""
    echo "1. Test the new installation at: http://sm-test.w5gle.us"
    echo "2. Compare with production at: http://sm.w5gle.us"
    echo "3. Test all functionality (login, favorites, node control, etc.)"
    echo "4. Check logs:"
    echo "   - Apache: sudo tail -f /var/log/apache2/sm-test_error.log"
    echo "   - Application: $TEST_DIR/logs/"
    echo ""
    echo "When testing is complete:"
    echo "  - To restore production: $0 --restore"
    echo "  - To keep test environment: $0 --keep"
    echo "  - To clean up: $0 --cleanup"
    echo ""
    echo "Backup location: $BACKUP_DIR"
    echo "Test directory: $TEST_DIR"
}

# Function to restore from backup
restore_from_backup() {
    print_status "Restoring from backup..."
    
    if [ ! -d "$BACKUP_DIR" ]; then
        print_error "No backup found. Please specify backup directory."
        exit 1
    fi
    
    restore_production
    cleanup_test
    
    print_success "Restoration complete!"
}

# Parse command line arguments
CLEAN_ENV=false
KEEP_ENV=false
RESTORE=false
CLEANUP=false

while [[ $# -gt 0 ]]; do
    case $1 in
        -t|--tarball)
            TARBALL_PATH="$2"
            shift 2
            ;;
        -c|--clean)
            CLEAN_ENV=true
            shift
            ;;
        -k|--keep)
            KEEP_ENV=true
            shift
            ;;
        --restore)
            RESTORE=true
            shift
            ;;
        --cleanup)
            CLEANUP=true
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
    print_status "Supermon-ng Test Update Script"
    print_status "=============================="
    
    # Check if we're restoring or cleaning up
    if [ "$RESTORE" = true ]; then
        restore_from_backup
        exit 0
    fi
    
    if [ "$CLEANUP" = true ]; then
        cleanup_test
        exit 0
    fi
    
    # Check prerequisites
    check_root
    check_production
    
    # Clean test environment if requested
    if [ "$CLEAN_ENV" = true ]; then
        clean_test_env
    fi
    
    # Create backup
    backup_production
    
    # Setup test environment
    setup_test_env
    
    # Test tarball if provided
    if [ -n "$TARBALL_PATH" ]; then
        test_tarball
        create_test_apache_config
    fi
    
    # Show testing instructions
    show_testing_instructions
    
    # Cleanup if not keeping environment
    if [ "$KEEP_ENV" = false ] && [ -n "$TARBALL_PATH" ]; then
        echo ""
        print_warning "Test environment will be cleaned up automatically."
        print_warning "Use -k flag to keep the test environment."
    fi
}

# Run main function
main "$@"
