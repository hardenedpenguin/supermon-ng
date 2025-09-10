#!/bin/bash

# Supermon-ng Quick Test Script
# Simple script to quickly test a tarball without full environment setup

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PRODUCTION_DIR="/var/www/html/supermon-ng"
QUICK_TEST_DIR="/tmp/supermon-ng-quick-test"
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
    echo "  -h, --help            Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 -t /tmp/supermon-ng-V4.0.4.tar.xz"
    echo ""
    echo "This script will:"
    echo "  1. Extract the tarball to a temporary directory"
    echo "  2. Show you the contents and structure"
    echo "  3. Allow you to inspect files before installation"
    echo "  4. Clean up automatically when done"
}

# Function to check if tarball exists
check_tarball() {
    if [ -z "$TARBALL_PATH" ]; then
        print_error "No tarball specified. Use -t option."
        show_usage
        exit 1
    fi
    
    if [ ! -f "$TARBALL_PATH" ]; then
        print_error "Tarball not found: $TARBALL_PATH"
        exit 1
    fi
}

# Function to extract and inspect tarball
inspect_tarball() {
    print_status "Extracting tarball for inspection..."
    
    # Clean up any existing test directory
    if [ -d "$QUICK_TEST_DIR" ]; then
        print_warning "Removing existing test directory..."
        rm -rf "$QUICK_TEST_DIR"
    fi
    
    # Create test directory
    mkdir -p "$QUICK_TEST_DIR"
    
    # Extract tarball
    print_status "Extracting: $TARBALL_PATH"
    tar -xJf "$TARBALL_PATH" -C "$QUICK_TEST_DIR"
    
    print_success "Tarball extracted to: $QUICK_TEST_DIR"
}

# Function to show tarball contents
show_contents() {
    print_status "Tarball Contents:"
    echo "=================="
    
    # Show directory structure
    echo ""
    print_status "Directory Structure:"
    find "$QUICK_TEST_DIR" -type d | head -20
    if [ $(find "$QUICK_TEST_DIR" -type d | wc -l) -gt 20 ]; then
        echo "... (showing first 20 directories)"
    fi
    
    # Show key files
    echo ""
    print_status "Key Files:"
    echo "install.sh: $(test -f "$QUICK_TEST_DIR/install.sh" && echo "✅ Present" || echo "❌ Missing")"
    echo "scripts/update.sh: $(test -f "$QUICK_TEST_DIR/scripts/update.sh" && echo "✅ Present" || echo "❌ Missing")"
    echo "scripts/migrate-config.php: $(test -f "$QUICK_TEST_DIR/scripts/migrate-config.php" && echo "✅ Present" || echo "❌ Missing")"
    echo "includes/common.inc: $(test -f "$QUICK_TEST_DIR/includes/common.inc" && echo "✅ Present" || echo "❌ Missing")"
    echo "frontend/dist/index.html: $(test -f "$QUICK_TEST_DIR/frontend/dist/index.html" && echo "✅ Present" || echo "❌ Missing")"
    echo "user_files/: $(test -d "$QUICK_TEST_DIR/user_files" && echo "✅ Present" || echo "❌ Missing")"
    
    # Show version information
    echo ""
    print_status "Version Information:"
    if [ -f "$QUICK_TEST_DIR/includes/common.inc" ]; then
        echo "Version: $(grep 'TITLE_LOGGED' "$QUICK_TEST_DIR/includes/common.inc" | grep -o 'V[0-9]\+\.[0-9]\+\.[0-9]\+' || echo "Unknown")"
        echo "Date: $(grep 'VERSION_DATE' "$QUICK_TEST_DIR/includes/common.inc" | cut -d'"' -f2 || echo "Unknown")"
    fi
    
    # Show file sizes
    echo ""
    print_status "File Sizes:"
    du -sh "$QUICK_TEST_DIR" 2>/dev/null || echo "Unable to determine size"
}

# Function to show differences
show_differences() {
    print_status "Comparing with current installation..."
    
    if [ ! -d "$PRODUCTION_DIR" ]; then
        print_warning "Production directory not found, skipping comparison"
        return
    fi
    
    echo ""
    print_status "Key Differences:"
    
    # Compare version
    if [ -f "$PRODUCTION_DIR/includes/common.inc" ] && [ -f "$QUICK_TEST_DIR/includes/common.inc" ]; then
        PROD_VERSION=$(grep 'TITLE_LOGGED' "$PRODUCTION_DIR/includes/common.inc" | grep -o 'V[0-9]\+\.[0-9]\+\.[0-9]\+' || echo "Unknown")
        TEST_VERSION=$(grep 'TITLE_LOGGED' "$QUICK_TEST_DIR/includes/common.inc" | grep -o 'V[0-9]\+\.[0-9]\+\.[0-9]\+' || echo "Unknown")
        echo "Version: $PROD_VERSION → $TEST_VERSION"
    fi
    
    # Compare file counts
    PROD_FILES=$(find "$PRODUCTION_DIR" -type f | wc -l)
    TEST_FILES=$(find "$QUICK_TEST_DIR" -type f | wc -l)
    echo "File count: $PROD_FILES → $TEST_FILES"
    
    # Check for new files
    echo ""
    print_status "New files in tarball:"
    find "$QUICK_TEST_DIR" -type f -newer "$PRODUCTION_DIR" 2>/dev/null | head -10 || echo "No new files detected"
}

# Function to show testing options
show_testing_options() {
    echo ""
    print_success "Tarball inspection complete!"
    echo ""
    echo "Testing Options:"
    echo "================"
    echo ""
    echo "1. Manual Testing:"
    echo "   - Browse to: $QUICK_TEST_DIR"
    echo "   - Check files, configurations, etc."
    echo ""
    echo "2. Full Test Environment:"
    echo "   - Run: ./scripts/test-update.sh -t $TARBALL_PATH"
    echo "   - Creates full test environment with Apache"
    echo ""
    echo "3. Update Production:"
    echo "   - Run: ./scripts/update.sh"
    echo "   - Updates your production installation"
    echo ""
    echo "4. Clean Up:"
    echo "   - This script will clean up automatically"
    echo "   - Or run: rm -rf $QUICK_TEST_DIR"
    echo ""
    echo "Press Enter to continue and clean up..."
    read -r
}

# Function to cleanup
cleanup() {
    print_status "Cleaning up test directory..."
    if [ -d "$QUICK_TEST_DIR" ]; then
        rm -rf "$QUICK_TEST_DIR"
        print_success "Cleanup complete"
    fi
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -t|--tarball)
            TARBALL_PATH="$2"
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
    print_status "Supermon-ng Quick Test Script"
    print_status "============================="
    
    # Check tarball
    check_tarball
    
    # Extract and inspect
    inspect_tarball
    show_contents
    show_differences
    show_testing_options
    
    # Cleanup
    cleanup
    
    print_success "Quick test complete!"
}

# Run main function
main "$@"
